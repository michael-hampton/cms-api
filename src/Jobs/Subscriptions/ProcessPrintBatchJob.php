<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\PrintBatchRepository;

/**
 * Phase 3 coordinator for a single PrintBatch.
 *
 * Dispatches export and label generation in parallel — they are
 * independent operations and do not need to be sequenced.
 *
 *   ExportPrintBatchJob  — generates the batch CSV/format file → SFTP
 *   GenerateLabelRunsJob — creates LabelRun records → dispatches GenerateLabelJob per subscriber
 *
 * This job intentionally does very little itself. It is the dispatch
 * point, not the execution point. Keeping it thin makes it trivially
 * retryable without risk of double-work.
 */
class ProcessPrintBatchJob extends BaseJob
{
    public string $queue = 'print';
    public int $tries = 3;

    public function __construct(
        private readonly PrintBatchRepository $batchRepository,
        private readonly Logger               $logger,
    )
    {
    }

    public function handle(
        int                  $batchId,
    ): void
    {
        $batch = $this->batchRepository->find($batchId);

        if (!$batch) {
            $this->logger->error('ProcessPrintBatchJob: batch not found', [
                'batch_id' => $batchId,
            ]);
            return;
        }

        if ($batch->isExported()) {
            $this->logger->info('ProcessPrintBatchJob: batch already exported, skipping', [
                'batch_id' => $batchId,
            ]);
            return;
        }

        $this->logger->info('ProcessPrintBatchJob: dispatching export and label jobs', [
            'batch_id' => $batchId,
        ]);

        // Parallel — both dispatched simultaneously, neither waits for the other.
        dispatch(ExportPrintBatchJob::for(), $batchId, $batch->issue_delivery_id);

        dispatch(GenerateLabelRunsJob::for(), $batchId);
    }
}