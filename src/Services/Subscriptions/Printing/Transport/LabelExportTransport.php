<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Transport;

/**
 * Transport contract for label file delivery.
 *
 * Intentionally separate from PrintExportTransport so that label
 * and batch exports can be routed to different destinations
 * (different SFTP servers, local paths, cloud buckets) via
 * independent container bindings without touching each other.
 *
 * Implementations:
 *   - SftpLabelExportTransport   (production)
 *   - LocalLabelExportTransport  (development / testing)
 *
 * Binding example (AppServiceProvider / PrintServiceProvider):
 *
 *   $this->app->bind(LabelExportTransport::class, function () {
 *       return new SftpLabelExportTransport(
 *           config('print.label_sftp.host'),
 *           config('print.label_sftp.port', 22),
 *           config('print.label_sftp.user'),
 *           config('print.label_sftp.password'),
 *           config('print.label_sftp.path'),
 *       );
 *   });
 */
interface LabelExportTransport
{
    /**
     * Upload or write label file contents to the transport destination.
     *
     * @param string $path Relative destination path / filename.
     * @param string $contents Raw file contents.
     *
     * @throws \RuntimeException When the upload / write fails.
     */
    public function upload(string $path, string $contents): void;

    /**
     * Human-readable transport identifier stored on the LabelRun record
     * for observability (e.g. "sftp://printer.example.com", "local").
     */
    public function identifier(): string;
}