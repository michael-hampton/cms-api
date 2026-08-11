<?php

namespace App\Jobs\Subscriptions;

use App\DTO\Subscriptions\WorkflowStageResult;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Subscriptions\Printing\PrintBatchExportService;
use App\Services\Workflow\WorkflowRunRecorderFactory;

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
    private PrintBatchRepository $batchRepository;
    private IssueDeliveryRepository $issueDeliveryRepository;
    private PrintBatchExportService $exportService;
    private WorkflowRunRecorderFactory $recorderFactory;
    private PrintRunRepository $printRunRepository;
    private Logger $logger;

    public function __construct(
        private readonly int $batchId,
        private readonly int $issueDeliveryId,
        private readonly bool $skipVendorDelivery = false,
    )
    {
    }

    public function handle(): void
    {
        $batch = $this->batchRepository->find($this->batchId);

        if (!$batch) {
            $this->logger->error('ExportPrintBatchJob: batch not found', [
                'batch_id' => $this->batchId,
            ]);
            return;
        }

        $issueDelivery = $this->issueDeliveryRepository->find($this->issueDeliveryId);

        if (!$issueDelivery) {
            $this->logger->error('ExportPrintBatchJob: issue delivery not found', [
                'batch_id' => $this->batchId,
                'issue_delivery_id' => $this->issueDeliveryId,
            ]);
            return;
        }

        try {
            $this->exportService->export($batch, $issueDelivery, $this->skipVendorDelivery);
        } catch (\Throwable $e) {
            $printRun = $this->printRunRepository->findActiveForIssueDelivery($batch->issue_delivery_id);

            if ($printRun) {
                $this->recorderFactory
                    ->forPrintRun($printRun, 'phase_3', WorkflowRunStatus::COMPLETE)
                    ->record(WorkflowStageResult::failed(
                        $e->getMessage(),
                        ['batch_id' => $this->batchId],
                    ));
            }

            throw $e;
        }

        $allBatches = $this->batchRepository->findByIssueDelivery($batch->issue_delivery_id);
        $allExported = $allBatches->every(fn($b) => $b->isExported());

        if (!$allExported) {
            return;
        }

        $printRun = $this->printRunRepository->findActiveForIssueDelivery($batch->issue_delivery_id);

        if (!$printRun) {
            $this->logger->warning('ExportPrintBatchJob: all batches exported but no active PrintRun found', [
                'issue_delivery_id' => $this->issueDeliveryId,
            ]);
            return;
        }

        $this->recorderFactory
            ->forPrintRun($printRun, 'phase_3', WorkflowRunStatus::COMPLETE)
            ->record(WorkflowStageResult::succeeded([
                'batch_count' => $allBatches->count(),
            ]));
    }
}