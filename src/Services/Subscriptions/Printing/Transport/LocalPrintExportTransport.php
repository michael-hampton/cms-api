<?php

namespace App\Services\Subscriptions\Printing\Transport;

class LocalPrintExportTransport implements PrintExportTransport
{
    private const DEFAULT_EXPORT_DIR =
        __DIR__ . '/../../../../exports/print';
    public function __construct(
        private readonly string $exportDirectory = self::DEFAULT_EXPORT_DIR,
    )
    {
    }

    public function upload(string $path, string $contents): void
    {
        $fullPath = rtrim($this->exportDirectory, '/') . '/' . ltrim($path, '/');
        $directory = dirname($fullPath);

        // suppress mkdir warnings, try to create the folder
        @mkdir($directory, 0777, true);

        // attempt to write the file, suppress warnings
        $result = @file_put_contents($fullPath, $contents);

        if ($result === false) {
            // optional: log the failure instead of throwing
            error_log("⚠️ Failed to write print export to: {$fullPath}");
        }
    }
}