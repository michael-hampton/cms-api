<?php

namespace App\Services\Subscriptions\Printing\Transport;

interface PrintExportTransport
{
    /**
     * Upload or write the given file contents to the transport destination.
     *
     * @param string $path Relative destination path / filename.
     * @param string $contents Raw file contents to write.
     *
     * @throws \RuntimeException When the upload / write fails.
     */
    public function upload(string $path, string $contents): void;

    /**
     * Whether a file exists at the given path on the transport destination.
     *
     * @param string $path Relative path / filename, as stored on PrintBatch::file_path.
     */
    public function exists(string $path): bool;

    /**
     * Size in bytes of the file at the given path, or null when the file
     * cannot be found or its size cannot be determined.
     *
     * @param string $path Relative path / filename, as stored on PrintBatch::file_path.
     */
    public function size(string $path): ?int;

    /**
     * Read and return the full contents of the file at the given path.
     *
     * @param string $path Relative path / filename, as stored on PrintBatch::file_path.
     *
     * @throws \RuntimeException When the file cannot be found or read.
     */
    public function download(string $path): string;
}