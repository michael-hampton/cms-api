<?php

namespace App\Framework\Queue;

use App\Framework\Support\Logger;
use Exception;

class Queue implements QueueInterface
{
    private static $jobs = [];
    private static $failedJobs = [];

    public static function push(JobInterface $job, array $data = []): void
    {
        self::$jobs[] = [
            'job' => $job,
            'data' => $data,
            'attempts' => 0,
            'created_at' => time(),
            'delay_until' => time() + $job->delay
        ];
    }

    public static function later(int $delay, JobInterface $job, array $data = []): void
    {
        $job->delay = $delay;
        self::push($job, $data);
    }

    public static function work(): void
    {
        while (!empty(self::$jobs)) {
            $item = array_shift(self::$jobs);

            // Check if job should be delayed
            if ($item['delay_until'] > time()) {
                self::$jobs[] = $item; // Put back in queue
                sleep(1);
                continue;
            }

            $job = $item['job'];
            $attempts = $item['attempts'] + 1;

            try {
                // Set timeout
                set_time_limit($job->timeout);

                $job->handle();

                Logger::info('Job completed', [
                    'job' => get_class($job),
                    'attempts' => $attempts
                ]);

            } catch (Exception $e) {
                Logger::error('Job failed', [
                    'job' => get_class($job),
                    'attempt' => $attempts,
                    'error' => $e->getMessage()
                ]);

                if ($attempts < $job->tries) {
                    // Retry with exponential backoff
                    $item['attempts'] = $attempts;
                    $item['delay_until'] = time() + (pow(2, $attempts) * 60); // 1min, 2min, 4min...
                    self::$jobs[] = $item;
                } else {
                    // Job failed permanently
                    $job->failed($e);
                    self::$failedJobs[] = [
                        'job' => serialize($job),
                        'error' => $e->getMessage(),
                        'failed_at' => time()
                    ];
                }
            }
        }
    }

    public static function size(): int
    {
        return count(self::$jobs);
    }

    public static function getFailedJobs(): array
    {
        return self::$failedJobs;
    }

    public static function retry(int $index): void
    {
        if (isset(self::$failedJobs[$index])) {
            $failedJob = self::$failedJobs[$index];
            $job = unserialize($failedJob['job']);

            self::push($job);
            unset(self::$failedJobs[$index]);
        }
    }
}