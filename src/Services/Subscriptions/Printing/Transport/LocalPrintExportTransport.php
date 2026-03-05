<?php

namespace App\Services\Subscriptions\Printing\Transport;

class LocalPrintExportTransport implements PrintExportTransport
{
    private const DEFAULT_EXPORT_DIR =
        __DIR__ . '/../../../../storage/exports/print';
    public function __construct(
        private readonly string $exportDirectory = self::DEFAULT_EXPORT_DIR,
    )
    {
    }

    public function upload(string $path, string $contents): void
    {
        $fullPath = rtrim($this->exportDirectory, '/') . '/' . ltrim($path, '/');
        $directory = dirname($fullPath);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Failed to create export directory: {$directory}");
        }

        $result = file_put_contents($fullPath, $contents);

        if ($result === false) {
            throw new \RuntimeException("Failed to write print export to: {$fullPath}");
        }
    }
}