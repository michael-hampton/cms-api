<?php

declare(strict_types=1);

namespace App\Framework\Queue;

use App\Framework\Support\Logger;

/**
 * Processes jobs from the database queue.
 *
 * Two execution modes:
 *
 *   Long-running (daemon):
 *     $worker->listen('default');
 *     Loops indefinitely, sleeping between empty polls.
 *     Suitable for a supervised process (systemd, supervisord).
 *
 *   Cron-tick / HTTP-triggered (bounded):
 *     $worker->runBatch('default', limit: 50);
 *     Processes up to $limit jobs then returns.
 *     Suitable for a cron entry or a short-lived HTTP handler.
 *
 * Backoff strategy is delegated to Job::backoffForAttempt(), so individual
 * jobs can customise their retry intervals without touching the worker.
 * DatabaseQueueDriver::release() accepts an explicit delay in seconds, so we
 * compute it here using the job's own backoff policy and pass the resolved
 * value to the driver.
 */
class QueueWorker
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly DatabaseQueueDriver $driver,
        private readonly Logger              $logger,
    )
    {
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Long-running daemon loop.
     *
     * @param string $queue Queue name to poll.
     * @param int $sleep Seconds to sleep when the queue is empty.
     * @param int $maxJobs Stop after processing this many jobs (0 = unlimited).
     */
    public function listen(string $queue = 'default', int $sleep = 3, int $maxJobs = 0): void
    {
        $this->registerSignalHandlers();

        $processed = 0;

        $this->logger->info('QueueWorker: started', [
            'queue' => $queue,
            'sleep' => $sleep,
            'max_jobs' => $maxJobs ?: 'unlimited',
        ]);

        while (!$this->shouldStop) {
            $job = $this->driver->pop($queue);

            if (!$job) {
                sleep($sleep);
                continue;
            }

            $this->process($job);
            $processed++;

            if ($maxJobs > 0 && $processed >= $maxJobs) {
                $this->logger->info('QueueWorker: max_jobs reached, stopping', [
                    'processed' => $processed,
                ]);
                break;
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        $this->logger->info('QueueWorker: stopped', ['processed' => $processed]);
    }

    /**
     * Cron-tick / HTTP-trigger mode: process up to $limit jobs then return.
     *
     * Returns the number of jobs processed.
     */
    public function runBatch(string $queue = 'default', int $limit = 50): int
    {
        $processed = 0;

        while ($processed < $limit) {
            $job = $this->driver->pop($queue);

            if (!$job) {
                break;
            }

            $this->process($job);

            $processed++;
        }

        $this->logger->info('QueueWorker: batch complete', [
            'queue' => $queue,
            'processed' => $processed,
        ]);

        return $processed;
    }

    /**
     * Allow external code to request a graceful stop (e.g. from a console command).
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    // -------------------------------------------------------------------------
    // Core processing
    // -------------------------------------------------------------------------

    private function process(QueuedJob $queuedJob): void
    {
        $record = $queuedJob->getRecord();
        $jobId = (int)$record['id'];
        $attempts = (int)$record['attempts'] + 1;

        $job = $this->deserialise($record['payload'], $jobId);

        dd($job);

        if ($job === null) {
            $this->driver->fail(
                $jobId,
                $record,
                new \RuntimeException('Failed to deserialise job payload'),
            );
            return;
        }

        $job->setQueuedJob($queuedJob);

        $this->logger->info('QueueWorker: processing job', [
            'job_id' => $jobId,
            'job' => $job::class,
            'attempt' => $attempts,
        ]);

        try {
            set_time_limit($job->timeout);
            $job->handle();
            $this->driver->acknowledge($jobId);

            $this->logger->info('QueueWorker: job complete', [
                'job_id' => $jobId,
                'job' => $job::class,
                'attempt' => $attempts,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('QueueWorker: job failed', [
                'job_id' => $jobId,
                'job' => $job::class,
                'attempt' => $attempts,
                'error' => $e->getMessage(),
            ]);

            if ($attempts < $job->tries) {
                // Resolve the delay via the job's own backoff policy, then pass
                // the computed seconds to the driver. This keeps the retry delay
                // logic in Job and avoids leaking it into the worker or driver.
                $delaySecs = $job->backoffForAttempt($attempts);
                $this->driver->release($jobId, $attempts, $delaySecs);

                $this->logger->info('QueueWorker: job released for retry', [
                    'job_id' => $jobId,
                    'attempt' => $attempts,
                    'retry_in_s' => $delaySecs,
                ]);
            } else {
                $job->failed($e);
                $this->driver->fail($jobId, $record, $e);
            }
        }
    }

    private function deserialise(string $payload, int $jobId): ?Job
    {
        try {
            $job = unserialize($payload);
        } catch (\Throwable) {
            $this->logger->error('QueueWorker: unserialize failed', ['job_id' => $jobId]);
            return null;
        }

        if (!$job instanceof Job) {
            $this->logger->error('QueueWorker: payload is not a Job instance', ['job_id' => $jobId]);
            return null;
        }

        return $job;
    }

    // -------------------------------------------------------------------------
    // Signal handling
    // -------------------------------------------------------------------------

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function (): void {
            $this->logger->info('QueueWorker: SIGTERM received, stopping after current job');
            $this->shouldStop = true;
        });

        pcntl_signal(SIGINT, function (): void {
            $this->logger->info('QueueWorker: SIGINT received, stopping after current job');
            $this->shouldStop = true;
        });
    }
}