<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

/**
 * A file's bytes plus enough metadata to serve it back over HTTP.
 * Returned by PrintBatchDownloadService / LabelRunDownloadService so
 * controllers stay transport-agnostic (local disk vs SFTP).
 */
final class ExportedFile
{
    public function __construct(
        public readonly string $filename,
        public readonly string $contents,
        public readonly string $mimeType = 'application/octet-stream',
    )
    {
    }
}
