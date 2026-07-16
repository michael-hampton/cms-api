<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing;

use App\DTO\Subscriptions\ExportedFile;
use App\Enums\Subscriptions\PrintExportFormat;
use App\Models\PrintBatch;
use App\Services\Subscriptions\Printing\Transport\PrintExportTransport;

/**
 * Resolves file metadata and contents for a PrintBatch, via whichever
 * PrintExportTransport is bound (local disk or SFTP) — the same one
 * PrintBatchExportService used to write the file in the first place.
 */
class PrintBatchDownloadService
{
    public function __construct(
        private readonly PrintExportTransport $transport,
    )
    {
    }

    /**
     * Whether the batch has an exported file that currently exists on the transport.
     */
    public function fileExists(PrintBatch $batch): bool
    {
        return $batch->file_path !== null && $this->transport->exists($batch->file_path);
    }

    /**
     * Size in bytes of the exported file, or null when there is no file
     * (not yet exported, or the file is missing from the transport).
     */
    public function fileSize(PrintBatch $batch): ?int
    {
        if ($batch->file_path === null) {
            return null;
        }

        return $this->transport->size($batch->file_path);
    }

    /**
     * @throws \RuntimeException When the batch has no file, or the file cannot be read.
     */
    public function download(PrintBatch $batch): ExportedFile
    {
        if ($batch->file_path === null) {
            throw new \RuntimeException("PrintBatch #{$batch->id} has no exported file yet");
        }

        $contents = $this->transport->download($batch->file_path);
        $format = PrintExportFormat::tryFrom($batch->format);

        return new ExportedFile(
            filename: basename($batch->file_path),
            contents: $contents,
            mimeType: $format?->mimeType() ?? 'application/octet-stream',
        );
    }
}
