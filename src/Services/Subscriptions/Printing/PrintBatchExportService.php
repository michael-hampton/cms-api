<?php

namespace App\Services\Subscriptions\Printing;

use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\PrintBatch;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\Printing\Format\PrintExportFormatStrategy;
use App\Services\Subscriptions\Printing\Transport\LocalPrintExportTransport;
use App\Services\Subscriptions\Printing\Transport\PrintExportTransport;

class PrintBatchExportService
{
    public function __construct(
        private readonly PrintFulfillmentRepository $fulfillmentRepository,
        private readonly PrintExportFormatStrategy  $formatStrategy,
        private readonly PrintExportTransport       $transport,
        private readonly Logger                     $logger,
        private readonly int                        $maxBatchSize = 5000,
    )
    {
    }

    /**
     * Export the given batch to the configured transport.
     *
     * Implements optimistic locking via status transitions to prevent
     * duplicate exports. Idempotent: already-exported batches are skipped.
     *
     * @param bool $skipVendorDelivery When true, bypasses the injected
     *   (potentially SFTP-to-vendor) transport and writes the file locally
     *   only — used for ad-hoc "preview" runs so a manually triggered
     *   export never reaches the print vendor. Operational ad-hoc runs and
     *   the normal scheduled path leave this false and use the configured
     *   transport as before.
     */
    public function export(PrintBatch $batch, IssueDelivery $issueDelivery, bool $skipVendorDelivery = false): void
    {
        if ($batch->isExported() || $batch->isExporting()) {
            $this->logger->info('print_batch_export_skipped — already exported or in progress', [
                'batch_id' => $batch->id,
                'status' => $batch->status,
            ]);
            return;
        }

        $batch->markExporting();

        $this->logger->info('print_batch_export_started', [
            'batch_id' => $batch->id,
            'issue_delivery_id' => $issueDelivery->id,
            'skip_vendor_delivery' => $skipVendorDelivery,
        ]);

        $transport = $skipVendorDelivery ? new LocalPrintExportTransport() : $this->transport;

        try {
            $fulfillments = $this->fulfillmentRepository->findByBatch($batch->id);

            if (count($fulfillments) > $this->maxBatchSize) {
                throw new \RuntimeException(
                    "Print batch #{$batch->id} exceeds maximum export size "
                    . "(found " . count($fulfillments) . ", limit {$this->maxBatchSize})"
                );
            }

            $issueSnapshot = [
                'id' => $issueDelivery->id,
                'title' => $issueDelivery->issue_title ?? null,
            ];

            $contents = $this->formatStrategy->generate($batch->id, $fulfillments, $issueSnapshot);

            $filename = $this->buildFilename($batch->id, $batch->export_attempt_count);

            $transport->upload($filename, $contents);

            if (!$skipVendorDelivery) {
                $this->fulfillmentRepository->markAllExported($batch->id);
            }

            $batch->markExported($filename);

            $this->logger->info('print_batch_export_completed', [
                'batch_id' => $batch->id,
                'filename' => $filename,
                'count' => count($fulfillments),
            ]);
        } catch (\Throwable $e) {
            $batch->markFailed();

            $this->logger->error('print_transport_upload_failed', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Deterministic versioned filename prevents collisions and enables re-export tracing.
     * Format: batch_{batch_id}_v{attempt}_{YYYYMMDD_HHmmss}.{ext}
     *
     * Examples:
     *   batch_42_v1_20260304_153020.csv   ← first export
     *   batch_42_v2_20260304_153025.csv   ← re-export after failure
     */
    private function buildFilename(int $batchId, int $attemptCount): string
    {
        $timestamp = (new \DateTimeImmutable())->format('Ymd_His');
        $extension = $this->formatStrategy->extension();

        return "batch_{$batchId}_v{$attemptCount}_{$timestamp}.{$extension}";
    }
}