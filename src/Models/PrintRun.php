<?php

namespace App\Models;

use App\Enums\Subscriptions\PrintRunStatus;

/**
 * A PrintRun represents the complete print output decision for one IssueDelivery.
 *
 * Relationship chain:
 *   IssueDelivery → PrintRun(s) → PrintBatch(es) → PrintFulfillment(s)
 *
 * A single IssueDelivery may have multiple PrintRuns over its lifetime (e.g. a
 * re-run after cancellation). Only one PrintRun per IssueDelivery should be in
 * a non-cancelled state at any time — enforced by the workflow, not the DB.
 *
 * @property int $id
 * @property int $issue_delivery_id
 * @property int|null $workflow_run_id    The WorkflowRun that created this PrintRun.
 * @property string $status             PrintRunStatus value.
 * @property bool $is_regional        Whether territory grouping was applied.
 * @property int|null $territory_id       Set when the run is pinned to one territory.
 * @property bool $driver_sync_enabled
 * @property string|null $driver_ref         External reference returned by syncToDriver.
 * @property string|null $driver_synced_at
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class PrintRun extends Model
{
    protected $table = 'print_runs';

    protected $fillable = [
        'issue_delivery_id',
        'workflow_run_id',
        'status',
        'is_regional',
        'territory_id',
        'driver_sync_enabled',
        'driver_ref',
        'driver_synced_at',
    ];

    protected $casts = [
        'is_regional' => 'boolean',
        'driver_sync_enabled' => 'boolean',
        'driver_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function issueDelivery(bool $relation = false)
    {
        return $this->belongsTo(IssueDelivery::class, 'issue_delivery_id', 'id', $relation);
    }

    public function batches(bool $relation = false)
    {
        return $this->hasMany(PrintBatch::class, 'print_run_id', 'id', $relation);
    }

    public function workflowRun(bool $relation = false)
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id', 'id', $relation);
    }

    // =========================================================================
    // State transitions
    // =========================================================================

    public function markComplete(): void
    {
        $this->update(['status' => PrintRunStatus::COMPLETE->value]);
    }

    public function markCancelled(): void
    {
        $this->update(['status' => PrintRunStatus::CANCELLED->value]);
    }

    public function markFailed(): void
    {
        $this->update(['status' => PrintRunStatus::FAILED->value]);
    }

    public function recordDriverSync(string $driverRef): void
    {
        $this->update([
            'driver_ref' => $driverRef,
            'driver_synced_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // State queries
    // =========================================================================

    public function isComplete(): bool
    {
        return $this->status === PrintRunStatus::COMPLETE->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === PrintRunStatus::CANCELLED->value;
    }

    public function canCancel(): bool
    {
        return $this->isPending();
    }

    public function isPending(): bool
    {
        return $this->status === PrintRunStatus::PENDING->value;
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopePending($query)
    {
        return $query->where('status', PrintRunStatus::PENDING->value);
    }

    public function scopeForIssueDelivery($query, int $issueDeliveryId)
    {
        return $query->where('issue_delivery_id', $issueDeliveryId);
    }
}