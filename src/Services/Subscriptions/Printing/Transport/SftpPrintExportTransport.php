<?php

namespace App\Services\Subscriptions\Printing\Transport;

use phpseclib3\Net\SFTP;

class SftpPrintExportTransport implements PrintExportTransport
{
    private const MAX_ATTEMPTS = 3;
    private const BASE_BACKOFF_MS = 500;   // milliseconds — doubles each retry
    private const UPLOAD_TIMEOUT_S = 60;    // seconds before set_time_limit forces abort

    public function __construct(
        private readonly string $host,
        private readonly int    $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $remotePath,
    )
    {
    }

    /**
     * Construct from environment configuration.
     * Credentials are never hardcoded; they must be provided via .env.
     */
    public static function fromConfig(): self
    {
        return new self(
            host: (string)config('print.sftp.host'),
            port: (int)config('print.sftp.port', 22),
            username: (string)config('print.sftp.user'),
            password: (string)config('print.sftp.password'),
            remotePath: (string)config('print.sftp.path'),
        );
    }

    /**
     * Upload file contents to the SFTP server.
     *
     * Retries up to MAX_ATTEMPTS times with exponential backoff.
     * Each attempt resets the PHP time limit so a hanging connection cannot
     * silently stall a queue worker indefinitely.
     *
     * @throws \RuntimeException When all attempts are exhausted.
     */
    public function upload(string $path, string $contents): void
    {
        $destination = rtrim($this->remotePath, '/') . '/' . ltrim($path, '/');
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                // Reset the time limit on each attempt so a hung connection
                // on attempt N doesn't eat into the budget for attempt N+1.
                set_time_limit(self::UPLOAD_TIMEOUT_S);

                $this->attemptUpload($destination, $contents);
                return; // success — stop retrying

            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt < self::MAX_ATTEMPTS) {
                    // Exponential backoff: 500ms, 1000ms, 2000ms …
                    $delayMs = self::BASE_BACKOFF_MS * (2 ** ($attempt - 1));
                    usleep($delayMs * 1000);
                }
            }
        }

        throw new \RuntimeException(
            "SFTP upload failed after " . self::MAX_ATTEMPTS . " attempts "
            . "for path: {$destination}. Last error: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Single upload attempt. Separated so retry logic in upload() stays clean.
     *
     * @throws \RuntimeException On auth failure or failed put.
     */
    private function attemptUpload(string $destination, string $contents): void
    {
        $sftp = new SFTP($this->host, $this->port);

        if (!$sftp->login($this->username, $this->password)) {
            throw new \RuntimeException("SFTP authentication failed for host: {$this->host}");
        }

        $uploaded = $sftp->put($destination, $contents, SFTP::SOURCE_STRING);

        if (!$uploaded) {
            throw new \RuntimeException("SFTP put failed for path: {$destination}");
        }
    }
}