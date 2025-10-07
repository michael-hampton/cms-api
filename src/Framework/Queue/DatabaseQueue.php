<?php

namespace App\Framework\Queue;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use Exception;

class DatabaseQueue implements QueueInterface
{
    private Database $db;
    private string $table = 'jobs';

    public function __construct(Database $database)
    {
        $this->db = $database;
        $this->ensureTableExists();
    }

    private function ensureTableExists(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                queue VARCHAR(255) DEFAULT 'default',
                payload TEXT NOT NULL,
                attempts INT DEFAULT 0,
                reserved_at INT NULL,
                available_at INT NOT NULL,
                created_at INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS failed_jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                queue VARCHAR(255) DEFAULT 'default',
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at INTEGER NOT NULL
            )
        ");
    }

    public function push(JobInterface $job, array $data = []): void
    {
        $payload = serialize([
            'job' => $job,
            'data' => $data
        ]);

        $now = time();
        $availableAt = $now + $job->delay;

        $stmt = $this->db->query("
            INSERT INTO jobs (queue, payload, attempts, available_at, created_at)
            VALUES (?, ?, 0, ?, ?)
        ", ['default', $payload, $availableAt, $now]);

        Logger::info('Job pushed to queue', [
            'job' => get_class($job),
            'id' => $this->db->lastInsertId()
        ]);
    }

    public function later(int $delay, JobInterface $job, array $data = []): void
    {
        $job->delay = $delay;
        $this->push($job, $data);
    }

    public function work(): void
    {
        $this->info('Queue worker started');

        while ($jobData = $this->getNextJob()) {
            $this->processJob($jobData);
        }

        $this->info('Queue is empty. Worker finished.');
    }

    private function getNextJob(): ?array
    {
        $result = $this->db->query("
            SELECT * FROM jobs
            WHERE available_at <= ? AND reserved_at IS NULL
            ORDER BY id ASC
            LIMIT 1
        ", [time()]);

        $result = $result->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            // Reserve the job
            $updateStmt = $this->db->query("
                UPDATE jobs SET reserved_at = ? WHERE id = ?
            ", [time(), $result['id']]);
        }

        return $result ?: null;
    }

    private function processJob(array $jobData): void
    {
        $jobId = $jobData['id'];
        $attempts = $jobData['attempts'] + 1;

        try {
            $payload = unserialize($jobData['payload']);
            $job = $payload['job'];

            // Set timeout
            set_time_limit($job->timeout);

            // Execute job
            $job->handle();

            // Delete job from queue
            $this->deleteJob($jobId);

            Logger::info('Job completed', [
                'job' => get_class($job),
                'id' => $jobId,
                'attempts' => $attempts
            ]);

        } catch (Exception $e) {
            Logger::error('Job failed', [
                'job_id' => $jobId,
                'attempt' => $attempts,
                'error' => $e->getMessage()
            ]);

            $payload = unserialize($jobData['payload']);
            $job = $payload['job'];

            if ($attempts < $job->tries) {
                // Retry with exponential backoff
                $this->retryJob($jobId, $attempts);
            } else {
                // Job failed permanently
                $job->failed($e);
                $this->moveToFailed($jobData, $e);
                $this->deleteJob($jobId);
            }
        }
    }

    private function retryJob(int $jobId, int $attempts): void
    {
        $delay = pow(2, $attempts) * 60; // Exponential backoff
        $availableAt = time() + $delay;

        $stmt = $this->db->query("
            UPDATE jobs
            SET attempts = ?, reserved_at = NULL, available_at = ?
            WHERE id = ?
        ", [$attempts, $availableAt, $jobId]);


        Logger::info('Job scheduled for retry', [
            'job_id' => $jobId,
            'attempts' => $attempts,
            'retry_in' => $delay . ' seconds'
        ]);
    }

    private function deleteJob(int $jobId): void
    {
        $this->db->query("DELETE FROM jobs WHERE id = ?", [$jobId]);
    }

    private function moveToFailed(array $jobData, Exception $e): void
    {
        $stmt = $this->db->query("
            INSERT INTO failed_jobs (queue, payload, exception, failed_at)
            VALUES (?, ?, ?, ?)
        ", [
            $jobData['queue'],
            $jobData['payload'],
            $e->getMessage() . "\n" . $e->getTraceAsString(),
            time()
        ]);
    }

    public function size(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM jobs WHERE reserved_at IS NULL");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    public function getFailedJobs(): array
    {
        $stmt = $this->db->query("SELECT * FROM failed_jobs ORDER BY failed_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function retry(int $id): void
    {
        $stmt = $this->db->query("SELECT * FROM failed_jobs WHERE id = ?", [$id]);
        $failedJob = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($failedJob) {
            $payload = unserialize($failedJob['payload']);
            $job = $payload['job'];
            $data = $payload['data'] ?? [];

            $this->push($job, $data);

            // Delete from failed jobs
            $deleteStmt = $this->db->query("DELETE FROM failed_jobs WHERE id = ?", [$id]);

            Logger::info('Failed job retried', ['id' => $id]);
        }
    }

    private function info(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
    }
}