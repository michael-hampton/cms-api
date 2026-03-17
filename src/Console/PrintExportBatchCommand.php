<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Services\Subscriptions\Printing\PrintBatchExportService;

class PrintExportBatchCommand extends Command
{
    use ReportsCommandResult;

    const FAILURE = 0;
    const SUCCESS = 1;

    protected $signature = 'print:export-batch {batchId : The ID of the print batch to export}';
    public $description = 'Re-generate and export a print batch.';

    public function __construct(
        private readonly PrintBatchRepository    $batchRepository,
        private readonly PrintBatchExportService $exportService,
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('print:export-batch');
        $batchId = (int)$this->argument('batchId');
        $batch = $this->batchRepository->find($batchId);

        if (!$batch) {
            $this->error("PrintBatch #{$batchId} not found.");
            return self::FAILURE;
        }

        $issueDelivery = IssueDelivery::find($batch->issue_delivery_id);

        try {
            if (!$issueDelivery) {
                throw new \Exception("IssueDelivery #{$batch->issue_delivery_id} not found.");
            }

            $batch->update(['status' => 'queued']);
            $this->exportService->export($batch->fresh(), $issueDelivery);

            $result->incrementSucceeded();
            $result->addMessage("Batch #{$batchId} exported successfully.");
        } catch (\Throwable $e) {
            $this->reportFailure(
                result: $result,
                message: "Export failed for batch #{$batchId}: {$e->getMessage()}",
                context: ['batch_id' => $batchId],
                throwable: $e
            );
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}