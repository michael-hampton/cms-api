<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Enums\Subscriptions\PrintRunStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Events\Subscriptions\AllFulfilmentsCreated;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Phase 1 entry point for the print pipeline.
 *
 * Responsibilities (only these — no more):
 *   1. Load the IssueDelivery.
 *   2. Find eligible PRINT subscriptions for this delivery.
 *   3. Create a PrintRun (status: fulfilling, total_chunks set).
 *   4. Chunk subscriptions into groups of config('print.chunk_size').
 *   5. Dispatch one CreateFulfilmentsChunkJob per chunk.
 *   6. Dispatch FulfilmentCompletionMonitorJob with a delay.
 *
 * Edge case — zero print subscriptions:
 *   If no print subscriptions are eligible, the PrintRun is created and
 *   immediately completed (no chunks dispatched, no monitor needed).
 *   AllFulfilmentsCreated is fired directly so Phase 2 still runs
 *   (BatchBuilderService will produce an empty result — idempotent).
 *
 * This job does NOT create fulfillment records. That is the chunk job's job.
 */
class TriggerPrintRunWorkflowJob extends BaseJob
{
    public string $queue = 'print';
    public int $tries = 3;

    public function __construct(
        private readonly int $issueDeliveryId,
    )
    {
    }

    public function handle(
        IssueDeliveryRepository $issueDeliveryRepository,
        PrintRunRepository      $printRunRepository,
        SubscriptionRepository  $subscriptionRepository,
        Logger                  $logger,
    ): void
    {
        $issueDelivery = $issueDeliveryRepository->find($this->issueDeliveryId);

        if (!$issueDelivery) {
            $logger->error('TriggerPrintRunWorkflowJob: IssueDelivery not found', [
                'issue_delivery_id' => $this->issueDeliveryId,
            ]);
            return;
        }

        // Cancel any existing pending PrintRuns for this delivery (idempotency).
        $printRunRepository->cancelAllPendingForIssueDelivery($issueDelivery->id);

        // Find all eligible print subscriptions.
        $printSubscriptions = $subscriptionRepository->findPrintSubscriptionsForIssueDelivery(
            $issueDelivery->id,
            $issueDelivery->subscription_plan_id,
        );

        $chunkSize = (int)config('print.chunk_size', 200);
        $chunks = $printSubscriptions->chunk($chunkSize);
        $totalChunks = $chunks->count();

        $printRun = $printRunRepository->create([
            'issue_delivery_id' => $issueDelivery->id,
            'status' => PrintRunStatus::PENDING->value,
            'is_regional' => false, // resolved later by BatchBuilderService
            'driver_sync_enabled' => false,
            'total_chunks' => $totalChunks,
            'fulfilled_chunks_count' => 0,
        ]);

        $logger->info('TriggerPrintRunWorkflowJob: PrintRun created', [
            'print_run_id' => $printRun->id,
            'issue_delivery_id' => $issueDelivery->id,
            'total_chunks' => $totalChunks,
            'subscription_count' => $printSubscriptions->count(),
        ]);

        // Zero subscriptions — skip straight to Phase 2.
        if ($totalChunks === 0) {
            $printRun->markFulfilling(0);
            $printRun->markBatching();

            event(new AllFulfilmentsCreated($printRun, 0));

            $logger->info('TriggerPrintRunWorkflowJob: no print subscriptions, skipping to Phase 2', [
                'print_run_id' => $printRun->id,
            ]);
            return;
        }

        $printRun->markFulfilling($totalChunks);

        // Dispatch one chunk job per group.
        foreach ($chunks as $chunkIndex => $chunk) {
            dispatch(CreateFulfilmentsChunkJob::for(), [
                'printRunId' => $printRun->id,
                'issueDeliveryId' => $issueDelivery->id,
                'subscriptionIds' => $chunk->pluck('id')->toArray(),
                'chunkIndex' => $chunkIndex,
            ])->onQueue(config('print.queue', 'print'));
        }

        // Safety net — fires after delay if chunks do not complete.
        $delayMinutes = (int)config('print.monitor_delay_minutes', 15);

        dispatch(FulfilmentCompletionMonitorJob::for(), $printRun->id)
            ->onQueue(config('print.queue', 'print'))
            ->delay(now_datetime()->addMinutes($delayMinutes));

        $logger->info('TriggerPrintRunWorkflowJob: chunk jobs dispatched', [
            'print_run_id' => $printRun->id,
            'chunks' => $totalChunks,
            'monitor_delay' => $delayMinutes,
        ]);
    }
}