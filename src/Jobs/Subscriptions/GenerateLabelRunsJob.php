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
 * Idempotent: uses LabelRunRepository::existsForSubscriptionIssueFulfilmentAndBatch()
 * to skip fulfillments that already have a LabelRun. Re-running this job
 * is safe — duplicate LabelRuns are never created, and GenerateLabelJob
 * skips already-complete runs.
 *
 * Format is resolved from config('print.label_format') and must match
 * a registered LabelExportFormat value.
 */
class GenerateLabelRunsJob extends BaseJob
{
    public ?string $queue = 'print';
    public int $tries = 3;
    private PrintBatchRepository $batchRepository;
    private PrintFulfillmentRepository $fulfillmentRepository;
    private LabelRunRepository $labelRunRepository;
    private Logger $logger;

    public function __construct(
        private readonly int                $batchId,
        private readonly ?LabelExportFormat $type = null,
    )
    {
    }

    public function handle(): void
    {
        $batch = $this->batchRepository->find($this->batchId);

        if (!$batch) {
            $this->logger->error('GenerateLabelRunsJob: batch not found', [
                'batch_id' => $this->batchId,
            ]);
            return;
        }

        $format = $this->type ?? LabelExportFormat::from(
            config('print.label_format', LabelExportFormat::Csv->value)
        );

        $fulfillments = $this->fulfillmentRepository->findByBatch($this->batchId);

        $dispatched = 0;
        $skipped = 0;

        foreach ($fulfillments as $fulfillment) {
            // Idempotency: skip if a LabelRun already exists for this
            // fulfillment + batch combination.
            if ($this->labelRunRepository->existsForSubscriptionIssueFulfilmentAndBatch(
                $fulfillment->subscription_issue_fulfilment_id,
                $this->batchId,
            )) {
                $skipped++;
                continue;
            }

            $labelRun = $this->labelRunRepository->createForSubscriptionIssueFulfilment(
                subscriptionIssueFulfilmentId: $fulfillment->subscription_issue_fulfilment_id,
                subscriptionId: $fulfillment->subscription_id,
                format: $format,
                printBatchId: $this->batchId,
            );

            dispatch(GenerateLabelJob::for((int)$labelRun->id));

            $dispatched++;
        }

        $this->logger->info('GenerateLabelRunsJob: label runs created', [
            'batch_id' => $this->batchId,
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ]);
    }
}