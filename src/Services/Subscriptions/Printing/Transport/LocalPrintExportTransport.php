<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Transport;

use Psr\Log\LoggerInterface;

class LocalPrintExportTransport implements PrintExportTransport
{
    private const DEFAULT_EXPORT_DIR =
        __DIR__ . '/../../../../storage/exports/print';

    public function __construct(
        private readonly string $exportDirectory = self::DEFAULT_EXPORT_DIR,
        private readonly ?LoggerInterface $logger = null,
    )
    {
    }

    public function upload(string $path, string $contents): void
    {
        $fullPath = rtrim($this->exportDirectory, '/') . '/' . ltrim($path, '/');
        $directory = dirname($fullPath);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $this->logger?->warning('Failed to create print export directory', [
                'directory' => $directory,
            ]);
            return;
        }

        $result = file_put_contents($fullPath, $contents);

        if ($result === false) {
            $this->logger?->warning('Failed to write print export file', [
                'path' => $fullPath,
            ]);
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
            throw new \RuntimeException("Print export file not found: {$fullPath}");
        }

        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            throw new \RuntimeException("Failed to read print export file: {$fullPath}");
        }

        return $contents;
    }

    private function resolvePath(string $path): string
    {
        return rtrim($this->exportDirectory, '/') . '/' . ltrim($path, '/');
    }
}