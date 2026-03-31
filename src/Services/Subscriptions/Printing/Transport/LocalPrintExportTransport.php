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
}