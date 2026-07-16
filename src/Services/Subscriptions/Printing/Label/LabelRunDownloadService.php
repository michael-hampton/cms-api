<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Label;

use App\DTO\Subscriptions\ExportedFile;
use App\Enums\Subscriptions\LabelExportFormat;
use App\Models\LabelRun;
use App\Services\Subscriptions\Printing\Transport\LocalLabelExportTransport;

/**
 * Resolves file metadata and contents for a LabelRun.
 *
 * Depends on the concrete LocalLabelExportTransport rather than the
 * LabelExportTransport interface, matching LabelGenerationService — the
 * container has no binding for the interface today, only local generation
 * is wired up. If/when SFTP label delivery is introduced, both this class
 * and LabelGenerationService should switch to the interface together.
 */
class LabelRunDownloadService
{
    public function __construct(
        private readonly LocalLabelExportTransport $transport,
    )
    {
    }

    public function fileExists(LabelRun $labelRun): bool
    {
        return $labelRun->file_path !== null && $this->transport->exists($labelRun->file_path);
    }

    public function fileSize(LabelRun $labelRun): ?int
    {
        if ($labelRun->file_path === null) {
            return null;
        }

        return $this->transport->size($labelRun->file_path);
    }

    /**
     * @throws \RuntimeException When the run has no file, or the file cannot be read.
     */
    public function download(LabelRun $labelRun): ExportedFile
    {
        if ($labelRun->file_path === null) {
            throw new \RuntimeException("LabelRun #{$labelRun->id} has no generated file yet");
        }

        $contents = $this->transport->download($labelRun->file_path);
        $format = LabelExportFormat::tryFrom($labelRun->format);

        return new ExportedFile(
            filename: basename($labelRun->file_path),
            contents: $contents,
            mimeType: $format?->mimeType() ?? 'application/octet-stream',
        );
    }
}
