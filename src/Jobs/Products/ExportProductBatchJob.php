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
    public string $queue = 'products';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly ProductBatchRepository    $batchRepository,
        private readonly ProductBatchExportService $exportService,
        private readonly Logger                    $logger,
    )
    {
    }

    public function handle(
        int $batchId,
    ): void
    {
        $batch = $this->batchRepository->find($batchId);

        if (!$batch) {
            $this->logger->error('ExportProductBatchJob: batch not found', [
                'batch_id' => $batchId,
            ]);
            return;
        }

        $this->exportService->export($batch);
    }
}