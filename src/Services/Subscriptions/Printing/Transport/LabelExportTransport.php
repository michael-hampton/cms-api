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
 * Binding example (ApiApplication::registerPrintTransportBindings()):
 *
 *   $this->container->bind(LabelExportTransport::class, function ($app) {
 *       return SftpLabelExportTransport::fromDefault(
 *           $app->make(PrintVendorConnectionRepository::class)
 *       );
 *   });
 *
 * Connection details (host/credentials/remote path) live on the active
 * default PrintVendorConnection for the label pipeline, managed via
 * PrintVendorConnectionController — not in env config. This allows
 * multiple print/label vendors, each with their own SFTP server, to be
 * configured and switched between without a deploy.
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

    /**
     * Whether a file exists at the given path on the transport destination.
     *
     * @param string $path Relative path / filename, as stored on LabelRun::file_path.
     */
    public function exists(string $path): bool;

    /**
     * Size in bytes of the file at the given path, or null when the file
     * cannot be found or its size cannot be determined.
     *
     * @param string $path Relative path / filename, as stored on LabelRun::file_path.
     */
    public function size(string $path): ?int;

    /**
     * Read and return the full contents of the file at the given path.
     *
     * @param string $path Relative path / filename, as stored on LabelRun::file_path.
     *
     * @throws \RuntimeException When the file cannot be found or read.
     */
    public function download(string $path): string;
}