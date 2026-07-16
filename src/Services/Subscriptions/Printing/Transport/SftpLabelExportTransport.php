<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Transport;

use phpseclib3\Net\SFTP;

/**
 * Delivers label files to a print supplier via SFTP.
 *
 * Can be bound to a different host/path than SftpPrintExportTransport
 * via independent container configuration — label and batch exports
 * are routed independently.
 *
 * Retry behaviour mirrors SftpPrintExportTransport:
 *   - Up to 3 attempts
 *   - Exponential backoff: 500 ms, 1 000 ms, 2 000 ms
 *   - PHP time limit reset on each attempt
 */
class SftpLabelExportTransport implements LabelExportTransport
{
    private const MAX_ATTEMPTS = 3;
    private const BASE_BACKOFF_MS = 500;
    private const UPLOAD_TIMEOUT = 60;

    public function __construct(
        private readonly string $host,
        private readonly int    $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $remotePath,
    )
    {
    }

    public static function fromConfig(): self
    {
        return new self(
            host: (string)config('print.label_sftp.host'),
            port: (int)config('print.label_sftp.port', 22),
            username: (string)config('print.label_sftp.user'),
            password: (string)config('print.label_sftp.password'),
            remotePath: (string)config('print.label_sftp.path'),
        );
    }

    public function upload(string $path, string $contents): void
    {
        $destination = rtrim($this->remotePath, '/') . '/' . ltrim($path, '/');
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                set_time_limit(self::UPLOAD_TIMEOUT);
                $this->attemptUpload($destination, $contents);
                return;
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt < self::MAX_ATTEMPTS) {
                    usleep(self::BASE_BACKOFF_MS * (2 ** ($attempt - 1)) * 1000);
                }
            }
        }

        throw new \RuntimeException(
            "Label SFTP upload failed after " . self::MAX_ATTEMPTS . " attempts "
            . "for path: {$destination}. Last error: " . $lastException->getMessage(),
            0,
            $lastException,
        );
    }

    public function identifier(): string
    {
        return "sftp://{$this->host}{$this->remotePath}";
    }

    private function attemptUpload(string $destination, string $contents): void
    {
        $sftp = new SFTP($this->host, $this->port);

        if (!$sftp->login($this->username, $this->password)) {
            throw new \RuntimeException("Label SFTP auth failed for host: {$this->host}");
        }

        if (!$sftp->put($destination, $contents, SFTP::SOURCE_STRING)) {
            throw new \RuntimeException("Label SFTP put failed for path: {$destination}");
        }
    }

    public function exists(string $path): bool
    {
        $destination = $this->resolvePath($path);

        return (bool)$this->withConnection(
            fn(SFTP $sftp) => $sftp->file_exists($destination)
        );
    }

    public function size(string $path): ?int
    {
        $destination = $this->resolvePath($path);

        return $this->withConnection(function (SFTP $sftp) use ($destination) {
            if (!$sftp->file_exists($destination)) {
                return null;
            }

            $size = $sftp->size($destination);

            return $size === false ? null : $size;
        });
    }

    /**
     * @throws \RuntimeException When the connection fails or the file cannot be found / read.
     */
    public function download(string $path): string
    {
        $destination = $this->resolvePath($path);
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                set_time_limit(self::UPLOAD_TIMEOUT);
                return $this->attemptDownload($destination);
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt < self::MAX_ATTEMPTS) {
                    usleep(self::BASE_BACKOFF_MS * (2 ** ($attempt - 1)) * 1000);
                }
            }
        }

        throw new \RuntimeException(
            "Label SFTP download failed after " . self::MAX_ATTEMPTS . " attempts "
            . "for path: {$destination}. Last error: " . $lastException->getMessage(),
            0,
            $lastException,
        );
    }

    private function attemptDownload(string $destination): string
    {
        $sftp = new SFTP($this->host, $this->port);

        if (!$sftp->login($this->username, $this->password)) {
            throw new \RuntimeException("Label SFTP auth failed for host: {$this->host}");
        }

        if (!$sftp->file_exists($destination)) {
            throw new \RuntimeException("Label SFTP file not found: {$destination}");
        }

        $contents = $sftp->get($destination);

        if ($contents === false) {
            throw new \RuntimeException("Label SFTP get failed for path: {$destination}");
        }

        return $contents;
    }

    private function resolvePath(string $path): string
    {
        return rtrim($this->remotePath, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Opens a single SFTP connection for a read-only operation and hands it
     * to the callback. Read operations (exists/size) are not retried: a
     * failed connection resolves to a "not found"-ish result, mirroring how
     * a missing/unreachable file would otherwise be reported.
     */
    private function withConnection(callable $callback): mixed
    {
        try {
            $sftp = new SFTP($this->host, $this->port);

            if (!$sftp->login($this->username, $this->password)) {
                return null;
            }

            return $callback($sftp);
        } catch (\Throwable) {
            return null;
        }
    }
}