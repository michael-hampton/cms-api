<?php

declare(strict_types=1);

namespace App\Framework\Queue;

/**
 * Queue driver that discards all pushed jobs.
 *
 * Used in unit tests to avoid requiring the jobs table (and a running worker)
 * when code paths fire events that dispatch follow-on jobs.
 */
class NullQueueDriver implements QueueDriverInterface
{
    public function push(Job $job): int
    {
      return 1;
    }

    public function pop(string $queue = 'default'): ?QueuedJob
    {
        return null;
    }

    public function acknowledge(int $jobId): void
    {
        // no-op
    }

    public function release(int $jobId, int $attempts): void
    {
        // no-op
    }

    public function fail(int $jobId, array $jobRecord, \Throwable $e): void
    {
        // no-op
    }

    public function size(string $queue = 'default'): int
    {
        return 0;
    }

    public function getFailedJobs(): array
    {
        return [];
    }

    public function retryFailed(int $failedJobId): void
    {
        // no-op
    }
}

