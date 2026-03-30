<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Subscriptions\PrintRunStatus;
use App\Framework\Database\Database;

/**
 * A PrintRun represents the complete print output decision for one IssueDelivery.
 *
 * Phase progression:
 *   pending → fulfilling → batching → batched → complete | failed | cancelled
 *
 * Phase 1 tracking (fulfilling):
 *   total_chunks           — set when PrintRun enters Fulfilling. Known up front
 *                            from the eligible subscription count / chunk size.
 *   fulfilled_chunks_count — atomically incremented by each CreateFulfilmentsChunkJob
 *                            via incrementFulfilledChunks(). When it reaches
 *                            total_chunks, AllFulfilmentsCreated is fired.
 *
 * @property int $id
 * @property int $issue_delivery_id
 * @property int|null $workflow_run_id
 * @property string $status
 * @property bool $is_regional
 * @property int|null $territory_id
 * @property bool $driver_sync_enabled
 * @property int $total_chunks
 * @property int $fulfilled_chunks_count
 * @property string|null $driver_ref
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
        'total_chunks',
        'fulfilled_chunks_count',
        'driver_ref',
        'driver_synced_at',
    ];

    protected $casts = [
        'is_regional' => 'boolean',
        'driver_sync_enabled' => 'boolean',
        'total_chunks' => 'integer',
        'fulfilled_chunks_count' => 'integer',
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
    // Phase 1 — fulfilment tracking
    // =========================================================================

    /**
     * Transition to Fulfilling and record how many chunks will be dispatched.
     * Called once by TriggerPrintRunWorkflowJob before any chunks are dispatched.
     */
    public function markFulfilling(int $totalChunks): void
    {
        $this->update([
            'status' => PrintRunStatus::FULFILLING->value,
            'total_chunks' => $totalChunks,
        ]);

        $this->total_chunks = $totalChunks;
    }

    /**
     * Atomically increment the fulfilled chunk counter.
     *
     * Returns the new count so the caller can decide whether to fire
     * AllFulfilmentsCreated without a separate SELECT.
     *
     * Uses a raw DB increment to avoid race conditions between concurrent
     * chunk workers — never do $this->fulfilled_chunks_count++ here.
     */
    public function incrementFulfilledChunks(int $chunkIndex): int
    {
        // Atomic increment of the counter — unchanged
        static::where('id', $this->id)->increment('fulfilled_chunks_count');

        // Append the index to the JSON array atomically
        static::where('id', $this->id)->update([
            'fulfilled_chunk_indexes' => Database::raw(
                "JSON_ARRAY_APPEND(fulfilled_chunk_indexes, '$', {$chunkIndex})"
            ),
        ]);

        $fresh = static::where('id', $this->id)
            ->selectRaw('fulfilled_chunks_count, fulfilled_chunk_indexes')
            ->first();

        $this->fulfilled_chunks_count = (int)$fresh->fulfilled_chunks_count;

        if ($fresh->fulfilled_chunk_indexes) {
            $this->fulfilled_chunk_indexes = json_decode($fresh->fulfilled_chunk_indexes, true);
        }

        return $this->fulfilled_chunks_count;
    }

    public function getMissingChunkIndexes(): array
    {
        $completed = $this->fulfilled_chunk_indexes ?? [];
        $all = range(0, $this->total_chunks - 1);

        return array_values(array_diff($all, $completed));
    }

    public function allChunksComplete(): bool
    {
        return $this->total_chunks > 0
            && $this->fulfilled_chunks_count >= $this->total_chunks;
    }

    // =========================================================================
    // Phase 2 — batch building
    // =========================================================================

    public function markBatching(): void
    {
        $this->update(['status' => PrintRunStatus::BATCHING->value]);
    }

    public function markBatched(): void
    {
        $this->update(['status' => PrintRunStatus::BATCHED->value]);
    }

    // =========================================================================
    // Terminal transitions
    // =========================================================================

    public function markComplete(): void
    {
        $this->update(['status' => PrintRunStatus::COMPLETE->value]);
    }

    public function markFailed(): void
    {
        $this->update(['status' => PrintRunStatus::FAILED->value]);
    }

    public function markCancelled(): void
    {
        $this->update(['status' => PrintRunStatus::CANCELLED->value]);
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

    public function isFulfilling(): bool
    {
        return $this->status === PrintRunStatus::FULFILLING->value;
    }

    public function isBatching(): bool
    {
        return $this->status === PrintRunStatus::BATCHING->value;
    }

    public function isBatched(): bool
    {
        return $this->status === PrintRunStatus::BATCHED->value;
    }

    public function isComplete(): bool
    {
        return $this->status === PrintRunStatus::COMPLETE->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === PrintRunStatus::CANCELLED->value;
    }

    public function isPending(): bool
    {
        return $this->status === PrintRunStatus::PENDING->value;
    }

    public function canCancel(): bool
    {
        return PrintRunStatus::from($this->status)->canCancel();
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopePending($query)
    {
        return $query->where('status', PrintRunStatus::PENDING->value);
    }

    public function scopeFulfilling($query)
    {
        return $query->where('status', PrintRunStatus::FULFILLING->value);
    }

    public function scopeForIssueDelivery($query, int $issueDeliveryId)
    {
        return $query->where('issue_delivery_id', $issueDeliveryId);
    }
}