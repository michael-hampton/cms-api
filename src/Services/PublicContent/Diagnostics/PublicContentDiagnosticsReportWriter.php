<?php

namespace App\Services\PublicContent\Diagnostics;

use RuntimeException;

class PublicContentDiagnosticsReportWriter
{
    public function append(array $record): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create diagnostics report directory: %s', $directory));
        }

        $json = json_encode(
            $record,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR,
        );

        if (@file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Unable to append diagnostics report: %s', $path));
        }
    }

    public function path(): string
    {
        $configured = trim((string) (getenv('PUBLIC_CONTENT_DIAGNOSTICS_REPORT_PATH') ?: ''));

        if ($configured !== '') {
            return $configured;
        }

        return dirname(__DIR__, 3) . '/storage/logs/public-content-widget-skips.jsonl';
    }
}