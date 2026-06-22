<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Subscriptions\LabelExportFormat;
use App\Enums\Subscriptions\LabelRunStatus;

/**
 * Represents a single printable label for one subscriber within a PrintBatch.
 *
 * Lifecycle:  pending → generating → complete | failed
 *
 * Relationships:
 *   - belongsTo SubscriptionIssueFulfilment  (WHY: business context — who this label is for)
 *   - belongsTo PrintBatch       (HOW: execution context — which batch produced it)
 *                                 PrintBatch is nullable: a label can exist before
 *                                 batching, and a batch can be retried independently.
 *
 * Do NOT add retry history, metadata logs, or attempt details here.
 * If that is needed later, introduce a LabelAttempt model.
 *
 * @property int $id
 * @property int $subscription_issue_fulfilment_id
 * @property int|null $print_batch_id
 * @property int $subscription_id
 * @property string $status              LabelRunStatus value
 * @property string $format              LabelExportFormat value
 * @property string|null $file_path
 * @property string|null $transport
 * @property int $attempt_count
 * @property string|null $generated_at
 * @property string|null $failure_reason
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class LabelRun extends Model
{
    protected $table = 'label_runs';

    protected $fillable = [
        'subscription_issue_fulfilment_id',
        'print_batch_id',
        'subscription_id',
        'status',
        'format',
        'file_path',
        'transport',
        'attempt_count',
        'generated_at',
        'failure_reason',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function subscriptionIssueFulfilment(bool $relation = false)
    {
        return $this->belongsTo(SubscriptionIssueFulfilment::class, 'subscription_issue_fulfilment_id', 'id', $relation);
    }

    public function printBatch(bool $relation = false)
    {
        return $this->belongsTo(PrintBatch::class, 'print_batch_id', 'id', $relation);
    }

    public function subscription(bool $relation = false)
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id', $relation);
    }

    // =========================================================================
    // State transitions
    // =========================================================================

    public function markGenerating(): void
    {
        $this->update([
            'status' => LabelRunStatus::Generating->value,
            'attempt_count' => $this->attempt_count + 1,
        ]);

        // Keep in-memory value consistent so callers don't need to reload.
        $this->attempt_count += 1;
    }

    public function markComplete(string $filePath, string $transport): void
    {
        $this->update([
            'status' => LabelRunStatus::Complete->value,
            'file_path' => $filePath,
            'transport' => $transport,
            'generated_at' => now_datetime()->format('Y-m-d H:i:s'),
            'failure_reason' => null,
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => LabelRunStatus::Failed->value,
            'failure_reason' => $reason,
        ]);
    }

    // =========================================================================
    // State queries
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === LabelRunStatus::Pending->value;
    }

    public function isGenerating(): bool
    {
        return $this->status === LabelRunStatus::Generating->value;
    }

    public function isComplete(): bool
    {
        return $this->status === LabelRunStatus::Complete->value;
    }

    public function isFailed(): bool
    {
        return $this->status === LabelRunStatus::Failed->value;
    }

    public function canRetry(int $maxAttempts = 3): bool
    {
        return $this->isFailed() && $this->attempt_count < $maxAttempts;
    }

    public function format(): LabelExportFormat
    {
        return LabelExportFormat::from($this->format);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopePending($query)
    {
        return $query->where('status', LabelRunStatus::Pending->value);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', LabelRunStatus::Failed->value);
    }

    public function scopeForBatch($query, int $batchId)
    {
        return $query->where('print_batch_id', $batchId);
    }

    public function scopeCanRetry($query, int $maxAttempts = 3)
    {
        return $query->where('status', LabelRunStatus::Failed->value)
            ->where('attempt_count', '<', $maxAttempts);
    }
}