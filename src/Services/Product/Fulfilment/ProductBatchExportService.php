<?php

declare(strict_types=1);

namespace App\Services\Product\Fulfilment;

use App\Framework\Support\Logger;
use App\Models\ProductBatch;
use App\Repositories\Product\ProductFulfilmentRepository;
use App\Services\Subscriptions\Printing\Format\PrintExportFormatStrategy;
use App\Services\Subscriptions\Printing\Transport\PrintExportTransport;

/**
 * Exports a ProductBatch to the configured transport.
 *
 * Parallel to PrintBatchExportService. That class is closed for modification —
 * this is a new service for the product pipeline.
 *
 * Reuses without modification:
 *   - PrintExportFormatStrategy interface (implemented by CsvProductExportFormatStrategy)
 *   - PrintExportTransport interface (LocalPrintExportTransport / SftpPrintExportTransport)
 *
 * Optimistic locking via status transitions prevents duplicate exports.
 */
class ProductBatchExportService
{
    public function __construct(
        private readonly ProductFulfilmentRepository $fulfilmentRepository,
        private readonly PrintExportFormatStrategy   $formatStrategy,
        private readonly PrintExportTransport        $transport,
        private readonly Logger                      $logger,
        private readonly int                         $maxBatchSize = 5000,
    )
    {
    }

    public function export(ProductBatch $batch): void
    {
        if ($batch->isExported() || $batch->isExporting()) {
            $this->logger->info('ProductBatchExportService: batch already exported or in progress', [
                'batch_id' => $batch->id,
                'status' => $batch->status,
            ]);
            return;
        }

        $batch->markExporting();

        $this->logger->info('ProductBatchExportService: export started', [
            'batch_id' => $batch->id,
        ]);

        try {
            $fulfilments = $this->fulfilmentRepository->findByBatch($batch->id);

            if (count($fulfilments) > $this->maxBatchSize) {
                throw new \RuntimeException(
                    "Product batch #{$batch->id} exceeds maximum export size "
                    . "(found " . count($fulfilments) . ", limit {$this->maxBatchSize})"
                );
            }

            // The interface carries an $issue parameter inherited from the print
            // pipeline. Products pass an empty snapshot — the strategy ignores it.
            $contents = $this->formatStrategy->generate($batch->id, $fulfilments, []);
            $filename = $this->buildFilename($batch->id, $batch->export_attempt_count);

            $this->transport->upload($filename, $contents);

            $this->fulfilmentRepository->markAllExported($batch->id);
            $batch->markExported($filename);

            $this->logger->info('ProductBatchExportService: export completed', [
                'batch_id' => $batch->id,
                'filename' => $filename,
                'count' => count($fulfilments),
            ]);
        } catch (\Throwable $e) {
            $batch->markFailed();

            $this->logger->error('ProductBatchExportService: export failed', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function buildFilename(int $batchId, int $attemptCount): string
    {
        $timestamp = (new \DateTimeImmutable())->format('Ymd_His');
        $extension = $this->formatStrategy->extension();

        return "product_batch_{$batchId}_v{$attemptCount}_{$timestamp}.{$extension}";
    }
}