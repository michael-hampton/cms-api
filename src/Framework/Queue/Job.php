<?php

declare(strict_types=1);

namespace App\Framework\Queue;

use App\Framework\Support\Logger;
use Exception;

/**
 * Framework base for all queueable jobs.
 *
 * Provides fluent queue configuration and backoff resolution.
 * Application jobs should extend BaseJob, not this class directly.
 */
abstract class Job implements JobInterface
{
    public int $tries = 3;
    public int $timeout = 60;
    public int $delay = 0;
    public ?string $queue = null;
    public ?string $connection = null;

    protected ?QueuedJob $queuedJob = null;

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function onConnection(string $connection): static
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * @param \DateTimeInterface|int $delay Seconds from now, or an absolute DateTime.
     */
    public function delay(\DateTimeInterface|int $delay): static
    {
        $this->delay = $delay instanceof \DateTimeInterface
            ? max(0, $delay->getTimestamp() - time())
            : max(0, $delay);

        return $this;
    }

    public function tries(int $tries): static
    {
        $this->tries = $tries;
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Backoff
    // -------------------------------------------------------------------------

    /**
     * Override to customise retry delays in seconds.
     *
     *   public function backoff(): array { return [30, 60, 120]; }
     *   public function backoff(): int   { return 60; }
     *
     * Default (empty array) → exponential: 60s, 120s, 240s …
     *
     * @return int[]|int
     */
    public function backoff(): array|int
    {
        return [];
    }

    final public function backoffForAttempt(int $attempt): int
    {
        $backoff = $this->backoff();

        if (is_int($backoff)) {
            return $backoff;
        }

        if (empty($backoff)) {
            return (int)(pow(2, $attempt - 1) * 60);
        }

        return (int)$backoff[min($attempt - 1, count($backoff) - 1)];
    }

    // -------------------------------------------------------------------------
    // Worker binding
    // -------------------------------------------------------------------------

    public function setQueuedJob(QueuedJob $job): void
    {
        $this->queuedJob = $job;
    }

    public function delete(): void
    {
        $this->queuedJob?->delete();
    }

    public function release(int $delay = 0): void
    {
        $this->queuedJob?->release($delay);
    }

    public function failed(Exception $exception): void
    {
        Logger::error('Job failed', [
            'job' => static::class,
            'error' => $exception->getMessage(),
        ]);
    }
}