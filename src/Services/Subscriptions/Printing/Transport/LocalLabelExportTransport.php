<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Transport;

class LocalLabelExportTransport implements LabelExportTransport
{
    private const DEFAULT_EXPORT_DIR =
        __DIR__ . '/../../../../storage/exports/labels';

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

    public function exists(string $path): bool
    {
        return is_file($this->resolvePath($path));
    }

    public function size(string $path): ?int
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            return null;
        }

        $size = filesize($fullPath);

        return $size === false ? null : $size;
    }

    public function download(string $path): string
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw new \RuntimeException("Label file not found: {$fullPath}");
        }

        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            throw new \RuntimeException("Failed to read label file: {$fullPath}");
        }

        return $contents;
    }

    private function resolvePath(string $path): string
    {
        return rtrim($this->exportDirectory, '/') . '/' . ltrim($path, '/');
    }
}