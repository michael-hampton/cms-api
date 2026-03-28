<?php

declare(strict_types=1);

namespace App\Jobs\Products;

use App\Enums\Products\ProductFulfilmentRunStatus;
use App\Events\Products\AllProductFulfilmentsCreated;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Product\ProductFulfilmentRunRepository;

/**
 * Phase 1 entry point for the product fulfilment pipeline.
 *
 * Parallel to TriggerPrintRunWorkflowJob. That class is closed for
 * modification — this is a new job operating on Orders rather than
 * Subscriptions + IssueDeliveries.
 *
 * Responsibilities (only these):
 *   1. Load the Order; guard against missing or already-fulfilled orders.
 *   2. Load eligible OrderLines for the order.
 *   3. Create a ProductFulfilmentRun (status: fulfilling, total_chunks set).
 *   4. Chunk order lines into groups of config('products.fulfilment.chunk_size').
 *   5. Dispatch one CreateProductFulfilmentsChunkJob per chunk.
 *   6. Dispatch ProductFulfilmentMonitorJob with a delay (safety net).
 *
 * Edge case — zero order lines:
 *   ProductFulfilmentRun created and immediately completed.
 *   AllProductFulfilmentsCreated fired directly so Phase 2 still runs.
 */
class TriggerProductFulfilmentJob extends BaseJob
{
    public string $queue = 'products';
    public int $tries = 3;

    public function __construct(
        private readonly OrderRepository                $orderRepository,
        private readonly OrderItemRepository            $orderLineRepository,
        private readonly ProductFulfilmentRunRepository $runRepository,
        private readonly Logger                         $logger,
    )
    {
    }

    public function handle(int $orderId): void
    {
        $order = $this->orderRepository->find($orderId);

        if (!$order) {
            $this->logger->error('TriggerProductFulfilmentJob: Order not found', [
                'order_id' => $orderId,
            ]);
            return;
        }

        $orderLines = $this->orderLineRepository->findFulfilableByOrder($orderId);

        if ($orderLines->isEmpty()) {
            $this->logger->info('TriggerProductFulfilmentJob: no fulfilable order lines', [
                'order_id' => $orderId,
            ]);
            return;
        }

        $chunkSize = (int)config('products.fulfilment.chunk_size', 100);
        $chunks = $orderLines->chunk($chunkSize);
        $totalChunks = $chunks->count();

        $run = $this->runRepository->create([
            'status' => ProductFulfilmentRunStatus::PENDING->value,
            'total_chunks' => $totalChunks,
            'fulfilled_chunks_count' => 0,
        ]);

        $this->logger->info('TriggerProductFulfilmentJob: ProductFulfilmentRun created', [
            'run_id' => $run->id,
            'order_id' => $orderId,
            'total_chunks' => $totalChunks,
            'line_count' => $orderLines->count(),
        ]);

        if ($totalChunks === 0) {
            $run->markFulfilling(0);
            event(new AllProductFulfilmentsCreated($run, 0));
            return;
        }

        $run->markFulfilling($totalChunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            dispatch(CreateProductFulfilmentsChunkJob::for(),
                $run->id,
                $orderId,
                $chunk->pluck('id')->toArray(),
                $chunkIndex,
            );
        }

        $delayMinutes = (int)config('products.fulfilment.monitor_delay_minutes', 15);

        dispatch(ProductFulfilmentMonitorJob::for(), $run->id);

        $this->logger->info('TriggerProductFulfilmentJob: chunk jobs dispatched', [
            'run_id' => $run->id,
            'chunks' => $totalChunks,
            'monitor_delay' => $delayMinutes,
        ]);
    }
}