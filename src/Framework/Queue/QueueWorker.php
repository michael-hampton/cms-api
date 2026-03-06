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
 *   Cron-tick (bounded):
 *     $worker->runBatch('default', limit: 50);
 *     Processes up to $limit jobs then exits.
 *     Suitable for a cron entry every minute.
 *
 * The worker never catches \Throwable inside handle() silently —
 * all failures are logged and the job is either released for retry
 * or moved to failed_jobs, matching the contract in DatabaseQueueDriver.
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
     * Registers SIGTERM/SIGINT handlers so the process exits cleanly after the
     * current job finishes rather than being killed mid-handle().
     *
     * @param string $queue Queue name to poll.
     * @param int $sleep Seconds to sleep when the queue is empty.
     * @param int $maxJobs Stop after processing this many jobs (0 = unlimited).
     *                          Useful to periodically restart the worker process
     *                          to avoid memory leaks.
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

            // Allow signal handlers to fire between jobs.
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        $this->logger->info('QueueWorker: stopped', ['processed' => $processed]);
    }

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

    private function process(QueuedJob $queuedJob): void
    {
        $record = $queuedJob->getRecord();
        $jobId = (int)$record['id'];
        $attempts = (int)$record['attempts'] + 1;

        $job = $this->deserialise($record['payload'], $jobId);

        if ($job === null) {
            // Payload is corrupt — move straight to failed so it doesn't loop.
            $this->driver->fail($jobId, $record, new \RuntimeException('Failed to deserialise job payload'));
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
                $this->driver->release($jobId, $attempts);
            } else {
                $job->failed($e);
                $this->driver->fail($jobId, $record, $e);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Core processing
    // -------------------------------------------------------------------------

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
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Cron-tick mode: process up to $limit jobs then return.
     *
     * Returns the number of jobs successfully processed.
     */
    public function runBatch(string $queue = 'default', int $limit = 50): int
    {
        $processed = 0;

        while ($processed < $limit) {
            $job = $this->driver->pop($queue);

            if (!$job) {
                break; // Queue empty.
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
}