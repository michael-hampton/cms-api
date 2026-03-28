<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Events\Subscriptions\PrintFulfilmentStalled;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\PrintRunRepository;

/**
 * Safety net for Phase 1.
 *
 * Dispatched by TriggerPrintRunWorkflowJob with a delay of
 * config('print.monitor_delay_minutes'). By the time this job runs,
 * all CreateFulfilmentsChunkJob workers should have completed and
 * AllFulfilmentsCreated should have been fired.
 *
 * If the PrintRun is still in Fulfilling status when this job runs,
 * one or more chunk jobs silently failed (dead-lettered, killed worker,
 * infrastructure failure). We fire PrintFulfilmentStalled so operators
 * can investigate and re-dispatch the missing chunks.
 *
 * No automated recovery is attempted — operator decides the action.
 * This is intentional: automated self-heal risks double-fulfilling
 * subscribers if the original chunks are still in-flight.
 */
class FulfilmentCompletionMonitorJob extends BaseJob
{
    public string $queue = 'print';
    public int $tries = 1; // Only run once — it's a point-in-time check.

    public function __construct(
        private readonly PrintRunRepository $printRunRepository,
        private readonly Logger             $logger,
    )
    {
    }

    public function handle(
        int $printRunId,
    ): void
    {
        $printRun = $this->printRunRepository->find($printRunId);

        if (!$printRun) {
            $this->logger->warning('FulfilmentCompletionMonitorJob: PrintRun not found', [
                'print_run_id' => $printRunId,
            ]);
            return;
        }

        // If the PrintRun has moved past Fulfilling, Phase 1 completed normally.
        if (!$printRun->isFulfilling()) {
            $this->logger->info('FulfilmentCompletionMonitorJob: PrintRun already past fulfilling phase', [
                'print_run_id' => $printRunId,
                'status' => $printRun->status,
            ]);
            return;
        }

        // Still fulfilling — something stalled.
        $completedChunks = $printRun->fulfilled_chunks_count;
        $totalChunks = $printRun->total_chunks;
        $delayMinutes = (int)config('print.monitor_delay_minutes', 15);

        $this->logger->error('FulfilmentCompletionMonitorJob: stall detected', [
            'print_run_id' => $printRunId,
            'completed_chunks' => $completedChunks,
            'total_chunks' => $totalChunks,
            'missing_chunks' => $totalChunks - $completedChunks,
        ]);

        event(new PrintFulfilmentStalled(
            printRun: $printRun,
            completedChunks: $completedChunks,
            totalChunks: $totalChunks,
            monitorDelayMinutes: $delayMinutes,
        ));
    }
}