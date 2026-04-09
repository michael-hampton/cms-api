<?php

declare(strict_types=1);

namespace App\Jobs\Products;

use App\Actions\Product\CreateProductFulfilmentAction;
use App\Events\Products\AllProductFulfilmentsCreated;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Product\ProductFulfilmentRunRepository;

/**
 * Phase 1 worker — processes one chunk of order lines.
 *
 * Parallel to CreateFulfilmentsChunkJob. That class is closed for
 * modification — this is a new job operating on Orders/OrderLines.
 *
 * Each job:
 *   1. Loads the ProductFulfilmentRun and Order (guards against stale state).
 *   2. Iterates order line IDs, calling CreateProductFulfilmentAction per line.
 *   3. Individual line failures are caught and logged — one bad address does
 *      not fail the whole chunk.
 *   4. Atomically increments ProductFulfilmentRun.fulfilled_chunks_count.
 *   5. If count equals total_chunks, fires AllProductFulfilmentsCreated.
 *
 * Idempotency:
 *   CreateProductFulfilmentAction guards against duplicate records.
 *   Re-running this job for the same chunk is safe.
 */
class CreateProductFulfilmentsChunkJob extends BaseJob
{
    public ?string $queue = 'products';
    public int $tries = 3;
    public int $backoff = 60;

    private ProductFulfilmentRunRepository $runRepository;
    private OrderRepository $orderRepository;
    private OrderItemRepository $orderLineRepository;
    private CreateProductFulfilmentAction $fulfilmentAction;
    private Logger $logger;

    public function __construct(
        private readonly int   $runId,
        private readonly int   $orderId,
        private readonly array $orderLineIds,
        private readonly int   $chunkIndex,
    )
    {
    }

    public function handle(): void
    {
        $run = $this->runRepository->find($this->runId);

        if (!$run) {
            $this->logger->error('CreateProductFulfilmentsChunkJob: run not found', [
                'run_id' => $this->runId,
                'chunk_index' => $this->chunkIndex,
            ]);
            return;
        }

        if ($run->isCancelled()) {
            $this->logger->info('CreateProductFulfilmentsChunkJob: run cancelled, skipping chunk', [
                'run_id' => $this->runId,
                'chunk_index' => $this->chunkIndex,
            ]);
            return;
        }

        $order = $this->orderRepository->find($this->orderId);

        if (!$order) {
            $this->logger->error('CreateProductFulfilmentsChunkJob: order not found', [
                'order_id' => $this->orderId,
                'run_id' => $this->runId,
            ]);
            return;
        }

        $created = 0;
        $failed = 0;

        foreach ($this->orderLineIds as $orderLineId) {
            $orderLine = $this->orderLineRepository->find($orderLineId);

            if (!$orderLine) {
                $this->logger->warning('CreateProductFulfilmentsChunkJob: order line not found', [
                    'order_line_id' => $orderLineId,
                    'run_id' => $this->runId,
                ]);
                $failed++;
                continue;
            }

            try {
                $this->fulfilmentAction->execute($order, $orderLine, $this->runId);
                $created++;
            } catch (\Throwable $e) {
                // Non-critical per line — address missing, validation failure, etc.
                // Log and continue so one bad record does not block the chunk.
                $failed++;
                $this->logger->error('CreateProductFulfilmentsChunkJob: fulfilment creation failed', [
                    'order_line_id' => $orderLineId,
                    'order_id' => $this->orderId,
                    'run_id' => $this->runId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('CreateProductFulfilmentsChunkJob: chunk processed', [
            'run_id' => $this->runId,
            'chunk_index' => $this->chunkIndex,
            'created' => $created,
            'failed' => $failed,
        ]);

        $newCount = $run->incrementFulfilledChunks();

        if ($run->allChunksComplete()) {
            $this->logger->info('CreateProductFulfilmentsChunkJob: all chunks complete', [
                'run_id' => $this->runId,
                'total_chunks' => $run->total_chunks,
            ]);

            event(new AllProductFulfilmentsCreated(
                fulfilmentRun: $run,
                totalFulfilments: $created,
            ));
        }
    }
}