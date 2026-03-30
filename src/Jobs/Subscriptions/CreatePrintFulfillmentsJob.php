<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\DTO\Subscriptions\WorkflowStageResult;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Events\Subscriptions\AllFulfilmentsCreated;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Workflow\WorkflowRunRecorderFactory;

/**
 * Queries all eligible print subscriptions for an IssueDelivery,
 * chunks them, marks the PrintRun as fulfilling, and dispatches
 * one CreateFulfilmentsChunkJob per chunk.
 *
 * Single reason to change: how we determine and distribute the
 * set of subscriptions that need a fulfillment record.
 *
 * Sits between TriggerPrintRunWorkflowJob (creates PrintRun + WorkflowRun) and
 * CreateFulfilmentsChunkJob (creates individual fulfillment records).
 *
 * WorkflowRun updates are observability only — a failure to update
 * WorkflowRun must never propagate to the pipeline. The recorder
 * handles this guarantee internally.
 *
 * Zero-subscription edge case:
 *   If no print subscriptions are eligible, PrintRun is transitioned
 *   directly to Batching and AllFulfilmentsCreated is fired so Phase 2
 *   still runs cleanly. No chunk jobs or monitor are dispatched.
 */
class CreatePrintFulfillmentsJob extends BaseJob
{
    public string $queue = 'print';
    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly PrintRunRepository         $printRunRepository,
        private readonly IssueDeliveryRepository    $issueDeliveryRepository,
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly WorkflowRunRecorderFactory $recorderFactory,
        private readonly Logger                     $logger,
    )
    {
    }

    public function handle(int $printRunId, int $issueDeliveryId): void
    {
        $printRun = $this->printRunRepository->find($printRunId);

        if (!$printRun) {
            $this->logger->error('CreatePrintFulfillmentsJob: PrintRun not found', [
                'print_run_id' => $printRunId,
            ]);
            return;
        }

        if ($printRun->isCancelled()) {
            $this->logger->info('CreatePrintFulfillmentsJob: PrintRun cancelled, aborting', [
                'print_run_id' => $printRunId,
            ]);
            return;
        }

        $issueDelivery = $this->issueDeliveryRepository->find($issueDeliveryId);

        if (!$issueDelivery) {
            $this->logger->error('CreatePrintFulfillmentsJob: IssueDelivery not found', [
                'print_run_id' => $printRunId,
                'issue_delivery_id' => $issueDeliveryId,
            ]);

            $printRun->markFailed();

            $this->recorderFactory
                ->forPrintRun($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
                ->record(WorkflowStageResult::failed(
                    'IssueDelivery not found: ' . $issueDeliveryId,
                    ['print_run_id' => $printRunId],
                ));

            return;
        }

        $referenceDate = $issueDelivery->on_sale_date
            ?? $issueDelivery->estimated_delivery_date
            ?? new \DateTime();

        $printSubscriptions = $this->subscriptionRepository->findPrintSubscriptionsForIssueDelivery(
            $issueDelivery->id,
            $issueDelivery->subscription_plan_id,
            $referenceDate,
        );

        $chunkSize = (int)config('print.chunk_size', 200);
        $chunks = $printSubscriptions->chunk($chunkSize);
        $totalChunks = $chunks->count();

        // Zero subscriptions — skip straight to Phase 2.
        if ($totalChunks === 0) {
            $printRun->markFulfilling(0);
            $printRun->markBatching();

            event(new AllFulfilmentsCreated(
                printRun: $printRun,
                totalFulfilments: 0,
            ));

            $this->recorderFactory
                ->forPrintRun($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
                ->record(WorkflowStageResult::succeeded([
                    'total_chunks' => 0,
                    'total_fulfilments' => 0,
                    'skipped_reason' => 'No eligible print subscriptions',
                ]));

            $this->logger->info('CreatePrintFulfillmentsJob: no print subscriptions, skipping to Phase 2', [
                'print_run_id' => $printRunId,
                'issue_delivery_id' => $issueDeliveryId,
            ]);

            return;
        }

        $printRun->markFulfilling($totalChunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            dispatch(
                CreateFulfilmentsChunkJob::for(),
                $printRunId,
                $issueDeliveryId,
                $chunk->pluck('id')->toArray(),
                $chunkIndex,
            );
        }

        $delayMinutes = (int)config('print.monitor_delay_minutes', 15);

        dispatch(FulfilmentCompletionMonitorJob::for(), $printRunId);

        $this->logger->info('CreatePrintFulfillmentsJob: chunk jobs dispatched', [
            'print_run_id' => $printRunId,
            'issue_delivery_id' => $issueDeliveryId,
            'subscription_count' => $printSubscriptions->count(),
            'total_chunks' => $totalChunks,
            'monitor_delay' => $delayMinutes,
        ]);
    }
}