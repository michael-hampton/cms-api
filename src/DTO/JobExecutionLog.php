<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Represents a single row in job_execution_logs.
 *
 * Not a full ORM model — just a typed value object with a static factory.
 * Repositories handle all persistence.
 */
class JobExecutionLog
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public readonly ?int    $id,
        public readonly string  $jobClass,
        public readonly string  $jobName,
        public string           $status,
        public readonly ?array  $params,
        public ?array           $result,
        public ?string          $output,
        public ?string          $errorMessage,
        public ?string          $errorTrace,
        public readonly string  $startedAt,
        public ?string          $finishedAt,
        public ?int             $durationMs,
        public readonly ?string $triggeredBy,
    )
    {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            jobClass: $row['job_class'],
            jobName: $row['job_name'],
            status: $row['status'],
            params: isset($row['params']) ? json_decode($row['params'], true) : null,
            result: isset($row['result']) ? json_decode($row['result'], true) : null,
            output: $row['output'] ?? null,
            errorMessage: $row['error_message'] ?? null,
            errorTrace: $row['error_trace'] ?? null,
            startedAt: $row['started_at'],
            finishedAt: isset($row['finished_at']) ? $row['finished_at'] : null,
            durationMs: isset($row['duration_ms']) ? (int)$row['duration_ms'] : null,
            triggeredBy: $row['triggered_by'] ?? null,
        );
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function hasFinished(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCEEDED, self::STATUS_FAILED], true);
    }
}