<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Transport;

class LocalLabelExportTransport implements LabelExportTransport
{
    private const DEFAULT_EXPORT_DIR =
        __DIR__ . '/../../../../../storage/exports/labels';

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
            throw new \RuntimeException("Failed to create label export directory: {$directory}");
        }

        if (file_put_contents($fullPath, $contents) === false) {
            throw new \RuntimeException("Failed to write label to: {$fullPath}");
        }
    }

    public function identifier(): string
    {
        return 'local:' . rtrim($this->exportDirectory, '/');
    }
}