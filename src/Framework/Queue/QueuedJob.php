<?php

namespace App\Framework\Queue;

class QueuedJob
{
    public function __construct(protected array $record)
    {
    }

    public function getJob(): Job
    {
        $job = unserialize($this->record['payload']);
        $job->setQueuedJob($this);
        return $job;
    }

    public function delete(): void
    {
        // already deleted in simple version
    }

    public function release(int $delay = 0): void
    {
        // reinsert with new available_at
    }

    public function fail(\Throwable $e): void
    {
        // move to failed_jobs table
    }
}