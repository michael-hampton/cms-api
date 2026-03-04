<?php

namespace App\Jobs\Subscriptions;

use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Services\Subscriptions\Printing\PrintBatchExportService;

/**
 * Dispatched by PrintDeliveryChannel after the fulfillment record is persisted
 * and the outer transaction has committed.
 *
 * Separating export into its own job means:
 *   - Transport failures do not roll back the fulfillment record.
 *   - The queue's retry mechanism handles transient SFTP / network failures.
 *   - Export work does not block the delivery pipeline.
 */
class ExportPrintBatchJob extends BaseJob
{
    public function __construct(
        private readonly PrintBatchRepository    $batchRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly PrintBatchExportService $exportService,
        private readonly Logger                  $logger,
    )
    {
    }

    public function handle(int $batchId, int $issueDeliveryId): void
    {
        $batch = $this->batchRepository->find($batchId);

        if (!$batch) {
            $this->logger->error('ExportPrintBatchJob: batch not found', [
                'batch_id' => $batchId,
            ]);
            return;
        }

        $issueDelivery = $this->issueDeliveryRepository->find($issueDeliveryId);

        if (!$issueDelivery) {
            $this->logger->error('ExportPrintBatchJob: issue delivery not found', [
                'batch_id' => $batchId,
                'issue_delivery_id' => $issueDeliveryId,
            ]);
            return;
        }

        // Export failures (transport errors) throw, causing the queue to retry
        // this job. PrintBatchExportService marks the batch failed internally
        // before re-throwing so the status reflects the failure in the DB.
        $this->exportService->export($batch, $issueDelivery);
    }
}