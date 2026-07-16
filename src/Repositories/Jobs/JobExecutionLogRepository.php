<?php

declare(strict_types=1);

namespace App\Repositories\Jobs;

use App\DTO\JobExecutionLog;
use App\Framework\Database\Database;

class JobExecutionLogRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Insert a new log row and return its generated ID.
     */
    public function create(
        string $jobClass,
        ?array $params,
        string $triggeredBy = 'system',
    ): int
    {
        $jobName = class_exists($jobClass)
            ? (new \ReflectionClass($jobClass))->getShortName()
            : basename(str_replace('\\', '/', $jobClass));

        return $this->database->insert('job_execution_logs', [
            'job_class' => $jobClass,
            'job_name' => $jobName,
            'status' => JobExecutionLog::STATUS_PENDING,
            'params' => $params !== null ? json_encode($params) : null,
            'triggered_by' => $triggeredBy,
            'started_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Record the `jobs` table row id a queue-mode log entry corresponds to,
     * so cancel()/terminate() can reach the underlying queued job later.
     */
    public function attachQueueJobId(int $id, ?int $queueJobId): void
    {
        if ($queueJobId === null) {
            return;
        }

        $this->database->update('job_execution_logs', [
            'queue_job_id' => $queueJobId,
        ], ['id' => $id]);
    }

    public function markRunning(int $id): void
    {
        $this->database->update('job_execution_logs', [
            'status' => JobExecutionLog::STATUS_RUNNING,
        ], ['id' => $id]);
    }

    /**
     * Mark a still-pending (not yet started) execution as cancelled.
     */
    public function markCancelled(int $id): void
    {
        $this->database->update('job_execution_logs', [
            'status' => JobExecutionLog::STATUS_CANCELLED,
            'finished_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    /**
     * Force-stop a running execution. Best effort: see WorkflowController::terminate()
     * for the caveat that a synchronously executing job cannot actually be
     * interrupted mid-flight from a separate HTTP request.
     */
    public function markTerminated(int $id): void
    {
        $this->database->update('job_execution_logs', [
            'status' => JobExecutionLog::STATUS_TERMINATED,
            'finished_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    /**
     * Reset a failed / cancelled / terminated execution back to pending so it
     * can be re-run, clearing out the previous attempt's result data.
     */
    public function reset(int $id): void
    {
        $this->database->update('job_execution_logs', [
            'status' => JobExecutionLog::STATUS_PENDING,
            'result' => null,
            'output' => null,
            'error_message' => null,
            'error_trace' => null,
            'finished_at' => null,
            'duration_ms' => null,
        ], ['id' => $id]);
    }

    public function markSucceeded(int $id, ?array $result, string $output, int $durationMs): void
    {
        $this->database->update('job_execution_logs', [
            'status' => JobExecutionLog::STATUS_SUCCEEDED,
            'result' => $result !== null ? json_encode($result) : null,
            'output' => $output,
            'finished_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'duration_ms' => $durationMs,
        ], ['id' => $id]);
    }

    public function markFailed(
        int    $id,
        string $errorMessage,
        string $errorTrace,
        string $output,
        int    $durationMs,
        ?array $result = null,
    ): void
    {
        $this->database->update('job_execution_logs', [
            'status' => JobExecutionLog::STATUS_FAILED,
            'error_message' => $errorMessage,
            'error_trace' => $errorTrace,
            'result' => $result !== null ? json_encode($result) : null,
            'output' => $output,
            'finished_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'duration_ms' => $durationMs,
        ], ['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    public function find(int $id): ?JobExecutionLog
    {
        $row = $this->database->fetchOne(
            'SELECT * FROM job_execution_logs WHERE id = :id',
            ['id' => $id],
        );

        return $row !== null ? JobExecutionLog::fromRow($row) : null;
    }

    /**
     * Paginated list, newest first.
     *
     * @return array{items: JobExecutionLog[], total: int, page: int, perPage: int}
     */
    public function paginate(
        int    $page = 1,
        int    $perPage = 25,
        string $jobClass = '',
        string $status = '',
    ): array
    {
        $where = [];
        $params = [];

        if ($jobClass !== '') {
            $where[] = 'job_class LIKE :job_class';
            $params['job_class'] = "%{$jobClass}%";
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $perPage;

        $countRow = $this->database->fetchOne(
            "SELECT COUNT(*) AS total FROM job_execution_logs {$whereClause}",
            $params,
        );

        $total = (int)($countRow['total'] ?? 0);

        $rows = $this->database->select(
            "SELECT * FROM job_execution_logs {$whereClause}
             ORDER BY started_at DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $perPage, 'offset' => $offset]),
        );

        return [
            'items' => array_map(fn(array $row) => JobExecutionLog::fromRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Execution history for a specific job class, newest first.
     *
     * @return JobExecutionLog[]
     */
    public function findByJobClass(string $jobClass, int $limit = 50): array
    {
        $rows = $this->database->select(
            'SELECT * FROM job_execution_logs
             WHERE job_class = :job_class
             ORDER BY started_at DESC
             LIMIT :limit',
            ['job_class' => $jobClass, 'limit' => $limit],
        );

        return array_map(fn(array $row) => JobExecutionLog::fromRow($row), $rows);
    }

    /**
     * Total number of execution logs matching the given (optional) filters.
     */
    public function count(string $jobClass = '', string $status = ''): int
    {
        $where = [];
        $params = [];

        if ($jobClass !== '') {
            $where[] = 'job_class LIKE :job_class';
            $params['job_class'] = "%{$jobClass}%";
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $row = $this->database->fetchOne(
            "SELECT COUNT(*) AS total FROM job_execution_logs {$whereClause}",
            $params,
        );

        return (int)($row['total'] ?? 0);
    }

    /**
     * Counts grouped by status, e.g. ['pending' => 2, 'running' => 1, 'succeeded' => 40, ...].
     * Statuses with zero executions are included with a count of 0.
     *
     * @return array<string, int>
     */
    public function countByStatus(string $jobClass = ''): array
    {
        $where = '';
        $params = [];

        if ($jobClass !== '') {
            $where = 'WHERE job_class LIKE :job_class';
            $params['job_class'] = "%{$jobClass}%";
        }

        $rows = $this->database->select(
            "SELECT status, COUNT(*) AS total FROM job_execution_logs {$where} GROUP BY status",
            $params,
        );

        $counts = array_fill_keys([
            JobExecutionLog::STATUS_PENDING,
            JobExecutionLog::STATUS_RUNNING,
            JobExecutionLog::STATUS_SUCCEEDED,
            JobExecutionLog::STATUS_FAILED,
            JobExecutionLog::STATUS_CANCELLED,
            JobExecutionLog::STATUS_TERMINATED,
        ], 0);

        foreach ($rows as $row) {
            $counts[$row['status']] = (int)$row['total'];
        }

        return $counts;
    }
}