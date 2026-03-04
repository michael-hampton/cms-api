<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Services\Subscriptions\Printing\PrintBatchExportService;

class PrintExportBatchCommand extends Command
{
    const FAILURE = 0;
    const SUCCESS = 1;
    protected $signature = 'print:export-batch {batchId : The ID of the print batch to export}';

    protected $description = 'Re-generate and export a print batch. Useful for debugging or recovering from transport failures.';

    public function __construct(
        private readonly PrintBatchRepository    $batchRepository,
        private readonly PrintBatchExportService $exportService,
    )
    {

    }

    public function handle(): int
    {
        $batchId = (int)$this->argument('batchId');

        $batch = $this->batchRepository->find($batchId);

        if (!$batch) {
            $this->error("PrintBatch #{$batchId} not found.");
            return self::FAILURE;
        }

        $issueDelivery = IssueDelivery::find($batch->issue_delivery_id);

        if (!$issueDelivery) {
            $this->error("IssueDelivery #{$batch->issue_delivery_id} not found for batch #{$batchId}.");
            return self::FAILURE;
        }

        $this->info("Exporting batch #{$batchId} (issue delivery #{$issueDelivery->id})...");

        try {
            // Reset batch status so export is not skipped by idempotency guard.
            $batch->update(['status' => 'queued']);
            $this->exportService->export($batch->fresh(), $issueDelivery);
            $this->info("Batch #{$batchId} exported successfully.");
        } catch (\Throwable $e) {
            $this->error("Export failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}