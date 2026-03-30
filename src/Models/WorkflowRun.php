<?php

namespace App\Models;

use App\Enums\Workflow\WorkflowRunStatus;

/**
 * Audit record for a single execution of any workflow.
 *
 * One row per workflow invocation. The `workflow_type` discriminator allows
 * a single table to serve all workflow types (PrintRunWorkflow, etc.).
 *
 * @property int $id
 * @property string $workflow_type   Fully-qualified class name of the workflow.
 * @property string $status          WorkflowRunStatus value.
 * @property array|null $input           Serialised trigger input payload.
 * @property array|null $summary         Serialised outcome summary written on completion.
 * @property string|null $error           Error message on failure.
 * @property \DateTime|null $started_at
 * @property \DateTime|null $completed_at
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class WorkflowRun extends Model
{
    protected $table = 'workflow_runs';

    protected $fillable = [
        'workflow_type',
        'status',
        'input',
        'summary',
        'error',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'input' => 'array',
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =========================================================================
    // Factories
    // =========================================================================

    public static function start(string $workflowType, array $input): Model
    {
        return static::create([
            'workflow_type' => $workflowType,
            'status' => WorkflowRunStatus::RUNNING->value,
            'input' => $input,
            'started_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // State transitions
    // =========================================================================

    public function markComplete(array $summary): void
    {
        $this->update([
            'status' => WorkflowRunStatus::COMPLETE->value,
            'summary' => $summary,
            'completed_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function markNoData(array $summary = []): void
    {
        $this->update([
            'status' => WorkflowRunStatus::NO_DATA->value,
            'summary' => $summary,
            'completed_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => WorkflowRunStatus::FAILED->value,
            'error' => $error,
            'completed_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // Queries
    // =========================================================================

    public function isRunning(): bool
    {
        return $this->status === WorkflowRunStatus::RUNNING->value;
    }

    // WorkflowRun model
    // WorkflowRun model — no advanceStage needed
    public function recordStage(WorkflowRunStatus $status, array $summary): void
    {
        if (WorkflowRunStatus::from($this->status)->isFinal()) {
            return; // never overwrite a terminal status
        }

        $this->update([
            'status' => $status->value,
            'summary' => array_merge($this->summary ?? [], $summary),
            'completed_at' => $status->isFinal()
                ? now_datetime()->format('Y-m-d H:i:s')
                : null,
        ]);
    }
}