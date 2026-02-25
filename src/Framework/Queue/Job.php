<?php

namespace App\Framework\Queue;

use App\Framework\Support\Logger;
use Exception;

abstract class Job implements JobInterface
{
    public int $tries = 3;
    public int $timeout = 60;
    public int $delay = 0;
    public ?string $queue = null;
    public ?string $connection = null;

    protected ?QueuedJob $queuedJob = null;

    // ---------- Fluent setters ----------

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

    public function delay(int $seconds): static
    {
        $this->delay = $seconds;
        return $this;
    }

    // ---------- Internal worker binding ----------

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
            'error' => $exception->getMessage()
        ]);
    }
}