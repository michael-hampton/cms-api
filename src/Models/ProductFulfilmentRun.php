<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Products\ProductFulfilmentRunStatus;

/**
 * Tracks the lifecycle of one product fulfilment cycle.
 *
 * Parallel to PrintRun in the print pipeline.
 *
 * Phase progression:
 *   pending → fulfilling → batching → batched → complete | failed | cancelled
 *
 * Phase 1 tracking mirrors PrintRun exactly:
 *   total_chunks           — set when entering Fulfilling
 *   fulfilled_chunks_count — atomically incremented per chunk job
 *
 * @property int $id
 * @property string $status
 * @property int $total_chunks
 * @property int $fulfilled_chunks_count
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class ProductFulfilmentRun extends Model
{
    protected $table = 'product_fulfilment_runs';

    protected $fillable = [
        'status',
        'total_chunks',
        'fulfilled_chunks_count',
    ];

    protected $casts = [
        'total_chunks' => 'integer',
        'fulfilled_chunks_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function markFulfilling(int $totalChunks): void
    {
        $this->update([
            'status' => ProductFulfilmentRunStatus::FULFILLING->value,
            'total_chunks' => $totalChunks,
        ]);
        $this->total_chunks = $totalChunks;
    }

    /**
     * Atomic increment — safe across concurrent chunk workers.
     * Returns the new count so the caller can detect completion.
     */
    public function incrementFulfilledChunks(): int
    {
        static::where('id', $this->id)->increment('fulfilled_chunks_count');

        $fresh = static::where('id', $this->id)
            ->selectRaw('fulfilled_chunks_count')
            ->first();

        $this->fulfilled_chunks_count = (int)$fresh->fulfilled_chunks_count;

        return $this->fulfilled_chunks_count;
    }

    public function allChunksComplete(): bool
    {
        return $this->total_chunks > 0
            && $this->fulfilled_chunks_count >= $this->total_chunks;
    }

    public function markBatching(): void
    {
        $this->update(['status' => ProductFulfilmentRunStatus::BATCHING->value]);
    }

    public function markBatched(): void
    {
        $this->update(['status' => ProductFulfilmentRunStatus::BATCHED->value]);
    }

    public function markComplete(): void
    {
        $this->update(['status' => ProductFulfilmentRunStatus::COMPLETE->value]);
    }

    public function markFailed(): void
    {
        $this->update(['status' => ProductFulfilmentRunStatus::FAILED->value]);
    }

    public function isFulfilling(): bool
    {
        return $this->status === ProductFulfilmentRunStatus::FULFILLING->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === ProductFulfilmentRunStatus::CANCELLED->value;
    }

    public function isComplete(): bool
    {
        return $this->status === ProductFulfilmentRunStatus::COMPLETE->value;
    }
}