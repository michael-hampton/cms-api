<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Enums\Subscriptions\LabelExportFormat;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\LabelRunRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\Printing\Label\LabelGenerationService;

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

    public function __construct()
    {
    }

    public function handle(
        PrintBatchRepository       $batchRepository,
        PrintFulfillmentRepository $fulfillmentRepository,
        LabelRunRepository         $labelRunRepository,
        Logger                     $logger,
        ?LabelExportFormat         $type = null,
        int                        $batchId,
    ): void
    {
        $batch = $batchRepository->find($batchId);

        if (!$batch) {
            $logger->error('GenerateLabelRunsJob: batch not found', [
                'batch_id' => $batchId,
            ]);
            return;
        }

        $format = $type ?? LabelExportFormat::from(
            config('print.label_format', LabelExportFormat::Csv->value)
        );

        $fulfillments = $fulfillmentRepository->findByBatch($batchId);

        $dispatched = 0;
        $skipped = 0;

        foreach ($fulfillments as $fulfillment) {
            // Idempotency: skip if a LabelRun already exists for this
            // fulfillment + batch combination.
            if ($labelRunRepository->existsForIssuesDeliveredAndBatch(
                $fulfillment->issues_delivered_id,
                $batchId,
            )) {
                $skipped++;
                continue;
            }

            $labelRun = $labelRunRepository->createForIssuesDelivered(
                issuesDeliveredId: $fulfillment->issues_delivered_id,
                subscriptionId: $fulfillment->subscription_id,
                format: $format,
                printBatchId: $batchId,
            );

            dispatch(GenerateLabelJob::for(), app(LabelRunRepository::class), app(LabelGenerationService::class), app(Logger::class), $labelRun->id);

            $dispatched++;
        }

        $logger->info('GenerateLabelRunsJob: label runs created', [
            'batch_id' => $batchId,
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ]);
    }
}