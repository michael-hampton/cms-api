<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Enums\Subscriptions\LabelExportFormat;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\LabelRunRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;

/**
 * Creates LabelRun records for every PrintFulfillment in a batch,
 * then dispatches one GenerateLabelJob per LabelRun.
 *
 * Idempotent: uses LabelRunRepository::existsForIssuesDeliveredAndBatch()
 * to skip fulfillments that already have a LabelRun. Re-running this job
 * is safe — duplicate LabelRuns are never created, and GenerateLabelJob
 * skips already-complete runs.
 *
 * Format is resolved from config('print.label_format') and must match
 * a registered LabelExportFormat value.
 */
class GenerateLabelRunsJob extends BaseJob
{
    public string $queue = 'print';
    public int $tries = 3;

    public function __construct(
        private readonly PrintBatchRepository       $batchRepository,
        private readonly PrintFulfillmentRepository $fulfillmentRepository,
        private readonly LabelRunRepository         $labelRunRepository,
        private readonly Logger                     $logger,
    )
    {
    }

    public function handle(
        int                        $batchId,
        ?LabelExportFormat $type = null,
    ): void
    {
        $batch = $this->batchRepository->find($batchId);

        if (!$batch) {
            $this->logger->error('GenerateLabelRunsJob: batch not found', [
                'batch_id' => $batchId,
            ]);
            return;
        }

        $format = $type ?? LabelExportFormat::from(
            config('print.label_format', LabelExportFormat::Csv->value)
        );

        $fulfillments = $this->fulfillmentRepository->findByBatch($batchId);

        $dispatched = 0;
        $skipped = 0;

        foreach ($fulfillments as $fulfillment) {
            // Idempotency: skip if a LabelRun already exists for this
            // fulfillment + batch combination.
            if ($this->labelRunRepository->existsForIssuesDeliveredAndBatch(
                $fulfillment->issues_delivered_id,
                $batchId,
            )) {
                $skipped++;
                continue;
            }

            $labelRun = $this->labelRunRepository->createForIssuesDelivered(
                issuesDeliveredId: $fulfillment->issues_delivered_id,
                subscriptionId: $fulfillment->subscription_id,
                format: $format,
                printBatchId: $batchId,
            );

            dispatch(GenerateLabelJob::for(), $labelRun->id);

            $dispatched++;
        }

        $this->logger->info('GenerateLabelRunsJob: label runs created', [
            'batch_id' => $batchId,
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ]);
    }
}