<?php

declare(strict_types=1);

namespace App\Jobs\Products;

use App\Events\Products\ProductFulfilmentStalled;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Product\ProductFulfilmentRunRepository;

/**
 * Safety net for Phase 1 of the product fulfilment pipeline.
 *
 * Parallel to FulfilmentCompletionMonitorJob. That class is closed for
 * modification — this is a new job for the product pipeline.
 *
 * Dispatched by TriggerProductFulfilmentJob with a configurable delay.
 * If the ProductFulfilmentRun is still in Fulfilling status when this fires,
 * one or more chunk jobs silently failed. ProductFulfilmentStalled is emitted
 * so operators can investigate.
 *
 * No automated recovery — operator decides the action.
 */
class ProductFulfilmentMonitorJob extends BaseJob
{
    public string $queue = 'products';
    public int $tries = 1;

    public function __construct(
        private readonly ProductFulfilmentRunRepository $runRepository,
        private readonly Logger                         $logger,
    )
    {
    }

    public function handle(
        int $runId,
    ): void
    {
        $run = $this->runRepository->find($runId);

        if (!$run) {
            $this->logger->warning('ProductFulfilmentMonitorJob: run not found', [
                'run_id' => $runId,
            ]);
            return;
        }

        if (!$run->isFulfilling()) {
            $this->logger->info('ProductFulfilmentMonitorJob: run past fulfilling phase', [
                'run_id' => $runId,
                'status' => $run->status,
            ]);
            return;
        }

        $completedChunks = $run->fulfilled_chunks_count;
        $totalChunks = $run->total_chunks;
        $delayMinutes = (int)config('products.fulfilment.monitor_delay_minutes', 15);

        $this->logger->error('ProductFulfilmentMonitorJob: stall detected', [
            'run_id' => $runId,
            'completed_chunks' => $completedChunks,
            'total_chunks' => $totalChunks,
            'missing_chunks' => $totalChunks - $completedChunks,
        ]);

        event(new ProductFulfilmentStalled(
            fulfilmentRun: $run,
            completedChunks: $completedChunks,
            totalChunks: $totalChunks,
            monitorDelayMinutes: $delayMinutes,
        ));
    }
}