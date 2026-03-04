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
}