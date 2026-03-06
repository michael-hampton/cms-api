<?php

declare(strict_types=1);

namespace App\Framework\Queue;

/**
 * Represents a job record that has been claimed from the queue.
 *
 * The worker holds a QueuedJob while processing. On completion it calls
 * DatabaseQueueDriver::acknowledge(), release(), or fail() directly —
 * the stub methods below are kept for backwards compatibility with any
 * code that still calls them via the InteractsWithQueue trait, but the
 * worker itself uses the driver methods so it has full control over the
 * DB state.
 */
class QueuedJob
{
    public function __construct(
        private readonly array $record,
    )
    {
    }

    /**
     * Deserialise the payload and bind this QueuedJob to the resulting Job
     * so the InteractsWithQueue trait methods work inside handle().
     */
    public function getJob(): Job
    {
        $job = unserialize($this->record['payload']);
        $job->setQueuedJob($this);
        return $job;
    }

    /**
     * Raw DB record — used by QueueWorker to read id, attempts, queue, payload.
     */
    public function getRecord(): array
    {
        return $this->record;
    }

    public function getId(): int
    {
        return (int)$this->record['id'];
    }

    public function getAttempts(): int
    {
        return (int)$this->record['attempts'];
    }

    // -------------------------------------------------------------------------
    // Stub methods for InteractsWithQueue trait compatibility.
    // The worker uses DatabaseQueueDriver directly; these are only here so
    // jobs that call $this->delete() / $this->release() inside handle() don't
    // fatal. Wire them up to the driver if you need that pattern.
    // -------------------------------------------------------------------------

    public function delete(): void
    {
    }

    public function release(int $delay = 0): void
    {
    }

    public function fail(\Throwable $e): void
    {
    }
}