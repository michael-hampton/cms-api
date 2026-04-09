<?php

declare(strict_types=1);

namespace App\Jobs\Products;

use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Product\ProductBatchRepository;
use App\Services\Product\Fulfilment\ProductBatchExportService;

/**
 * Exports a single ProductBatch.
 *
 * Parallel to ExportPrintBatchJob. That class is closed for modification.
 * Dispatched by BuildProductBatchesJob once per batch.
 *
 * Transport failures cause a retry via the queue's backoff mechanism.
 * ProductBatchExportService marks the batch failed before re-throwing
 * so every attempt is observable in the database.
 */
class ExportProductBatchJob extends BaseJob
{
    public ?string $queue = 'products';
    public int $tries = 3;
    public int $backoff = 60;
    private ProductBatchRepository $batchRepository;
    private ProductBatchExportService $exportService;
    private Logger $logger;

    public function __construct(
        private readonly int $batchId,
    )
    {
    }

    public function handle(): void
    {
        $batch = $this->batchRepository->find($this->batchId);

        if (!$batch) {
            $this->logger->error('ExportProductBatchJob: batch not found', [
                'batch_id' => $this->batchId,
            ]);
            return;
        }

        $this->exportService->export($batch);
    }
}