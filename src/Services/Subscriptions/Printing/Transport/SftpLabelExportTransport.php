<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Transport;

use App\Enums\Subscriptions\PrintVendorConnectionType;
use App\Models\PrintVendorConnection;
use App\Repositories\Subscriptions\PrintVendorConnectionRepository;
use phpseclib3\Net\SFTP;
use RuntimeException;

/**
 * Delivers label files to a print supplier via SFTP.
 *
 * Connection details (host/port/credentials/path) come from a
 * PrintVendorConnection row — see fromVendorConnection() / fromDefault().
 * This replaces the previous hardcoded print.label_sftp.* env config:
 * different vendors can now each have their own server, and credentials
 * can be rotated by an admin (see PrintVendorConnectionController)
 * without a deploy.
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

    /**
     * Build a transport for a specific, already-resolved vendor connection.
     */
    public static function fromVendorConnection(PrintVendorConnection $connection): self
    {
        return new self(
            host: $connection->host,
            port: $connection->port,
            username: $connection->username,
            password: (string)$connection->password,
            remotePath: $connection->remote_path,
        );
    }

    /**
     * Build a transport from the active default vendor connection for the
     * label pipeline (connection_type 'label' or 'both'). This is what
     * the container binding for LabelExportTransport uses — see
     * ApiApplication::registerPrintTransportBindings().
     *
     * @throws RuntimeException When no active default label connection is
     *                          configured, so a misconfiguration surfaces
     *                          immediately rather than silently uploading
     *                          nowhere / to a stale host.
     */
    public static function fromDefault(PrintVendorConnectionRepository $repository): self
    {
        $connection = $repository->findDefaultForType(PrintVendorConnectionType::Label);

        if (!$connection) {
            throw new RuntimeException(
                'No active default print vendor connection is configured for the label pipeline. '
                . 'Add one via the print vendor connections admin screen and mark it as default.'
            );
        }

        return self::fromVendorConnection($connection);
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