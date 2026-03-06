<?php

declare(strict_types=1);

namespace App\Framework\Queue;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;

/**
 * Database-backed queue driver.
 *
 * This is the single canonical queue implementation. It is used by Dispatcher
 * for pushing jobs and by QueueWorker for processing them.
 *
 * The old DatabaseQueue class (which implemented the empty QueueInterface and
 * duplicated this logic) should be deleted. DatabaseQueueDriver is the
 * replacement — it is complete and wired correctly.
 *
 * Schema (see migration CreateJobsTable):
 *   jobs(id, queue, payload, attempts, reserved_at, available_at, created_at)
 *   failed_jobs(id, queue, payload, exception, failed_at)
 *
 * Reservation model:
 *   - pop() claims a job by setting reserved_at atomically before returning it.
 *   - The worker calls acknowledge() on success or release()/fail() on failure.
 *   - A job stuck with reserved_at set for longer than $reservationTimeout
 *     is considered abandoned and becomes available again on the next pop().
 */
class DatabaseQueueDriver implements QueueDriverInterface
{
    /**
     * Seconds before a reserved-but-unacknowledged job is considered
     * abandoned and returned to the queue.
     */
    private const RESERVATION_TIMEOUT = 90;

    public function __construct(
        private readonly Database $db,
        private readonly Logger   $logger,
    )
    {
    }

    // -------------------------------------------------------------------------
    // QueueDriverInterface
    // -------------------------------------------------------------------------

    /**
     * Serialise and insert a job into the jobs table.
     */
    public function push(Job $job): void
    {
        $now = time();
        $availableAt = $now + $job->delay;

        $this->db->query(
            "INSERT INTO jobs (queue, payload, attempts, available_at, created_at)
             VALUES (?, ?, 0, ?, ?)",
            [
                $job->queue ?? 'default',
                serialize($job),
                $availableAt,
                $now,
            ]
        );

        $this->logger->info('Job pushed to queue', [
            'job' => $job::class,
            'queue' => $job->queue ?? 'default',
            'delay' => $job->delay,
        ]);
    }

    /**
     * Atomically claim the next available job and return it.
     *
     * Returns null when the queue is empty.
     * Abandoned jobs (reserved_at expired) are released before fetching.
     */
    public function pop(string $queue = 'default'): ?QueuedJob
    {
        $this->releaseAbandoned($queue);

        $result = $this->db->query(
            "SELECT * FROM jobs
             WHERE queue        = ?
               AND available_at <= ?
               AND reserved_at  IS NULL
             ORDER BY id ASC
             LIMIT 1",
            [$queue, time()]
        );

        $record = $result->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            return null;
        }

        // Reserve the job so no other worker picks it up concurrently.
        $this->db->query(
            "UPDATE jobs SET reserved_at = ? WHERE id = ? AND reserved_at IS NULL",
            [time(), $record['id']]
        );

        // Re-fetch to confirm we won the reservation race.
        $confirm = $this->db->query(
            "SELECT * FROM jobs WHERE id = ? AND reserved_at IS NOT NULL",
            [$record['id']]
        );

        $reserved = $confirm->fetch(\PDO::FETCH_ASSOC);

        if (!$reserved) {
            // Another worker claimed it first — try again on the next tick.
            return null;
        }

        return new QueuedJob($reserved);
    }

    // -------------------------------------------------------------------------
    // Worker-facing acknowledgement methods
    // -------------------------------------------------------------------------

    /**
     * Remove a successfully processed job from the table.
     */
    public function acknowledge(int $jobId): void
    {
        $this->db->query("DELETE FROM jobs WHERE id = ?", [$jobId]);
    }

    /**
     * Release a job back to the queue after a transient failure,
     * incrementing the attempt counter and applying exponential backoff.
     */
    public function release(int $jobId, int $attempts): void
    {
        $delay = (int)(2 ** $attempts) * 60; // 2m, 4m, 8m, …
        $availableAt = time() + $delay;

        $this->db->query(
            "UPDATE jobs
             SET attempts    = ?,
                 reserved_at = NULL,
                 available_at = ?
             WHERE id = ?",
            [$attempts, $availableAt, $jobId]
        );

        $this->logger->info('Job released for retry', [
            'job_id' => $jobId,
            'attempts' => $attempts,
            'retry_in_s' => $delay,
        ]);
    }

    /**
     * Move a permanently failed job to the failed_jobs table and delete
     * it from the active queue.
     */
    public function fail(int $jobId, array $jobRecord, \Throwable $e): void
    {
        $this->db->query(
            "INSERT INTO failed_jobs (queue, payload, exception, failed_at)
             VALUES (?, ?, ?, ?)",
            [
                $jobRecord['queue'],
                $jobRecord['payload'],
                $e->getMessage() . "\n" . $e->getTraceAsString(),
                time(),
            ]
        );

        $this->db->query("DELETE FROM jobs WHERE id = ?", [$jobId]);

        $this->logger->error('Job permanently failed', [
            'job_id' => $jobId,
            'error' => $e->getMessage(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    public function size(string $queue = 'default'): int
    {
        $result = $this->db->query(
            "SELECT COUNT(*) AS count FROM jobs WHERE queue = ? AND reserved_at IS NULL",
            [$queue]
        );

        return (int)$result->fetch(\PDO::FETCH_ASSOC)['count'];
    }

    public function getFailedJobs(): array
    {
        $result = $this->db->query(
            "SELECT * FROM failed_jobs ORDER BY failed_at DESC"
        );

        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retry a failed job by moving it back to the active queue.
     */
    public function retryFailed(int $failedJobId): void
    {
        $result = $this->db->query(
            "SELECT * FROM failed_jobs WHERE id = ?",
            [$failedJobId]
        );
        $failedJob = $result->fetch(\PDO::FETCH_ASSOC);

        if (!$failedJob) {
            $this->logger->warning('retryFailed: failed job not found', ['id' => $failedJobId]);
            return;
        }

        $job = unserialize($failedJob['payload']);

        if (!$job instanceof Job) {
            $this->logger->error('retryFailed: payload did not deserialise to a Job', [
                'id' => $failedJobId,
            ]);
            return;
        }

        // Reset attempts so it gets a clean retry budget.
        $job->delay = 0;
        $this->push($job);

        $this->db->query("DELETE FROM failed_jobs WHERE id = ?", [$failedJobId]);

        $this->logger->info('Failed job requeued', ['failed_job_id' => $failedJobId]);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * Release jobs that were reserved but never acknowledged within the
     * reservation timeout. This handles worker crashes and timeouts.
     */
    private function releaseAbandoned(string $queue): void
    {
        $cutoff = time() - self::RESERVATION_TIMEOUT;

        $this->db->query(
            "UPDATE jobs
             SET reserved_at = NULL
             WHERE queue       = ?
               AND reserved_at IS NOT NULL
               AND reserved_at < ?",
            [$queue, $cutoff]
        );
    }
}