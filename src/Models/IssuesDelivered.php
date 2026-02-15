<?php

namespace App\Models;

use App\Enums\Subscriptions\IssueDeliveredStatus;

class IssuesDelivered extends Model
{
    protected $table = 'issues_delivered';

    protected $fillable = [
        'subscription_id',
        'issue_delivery_id',
        'status',
        'attempts',
        'delivered_at',
        'failure_reason',
    ];

    protected $casts = [
        'status' => 'integer',
        'attempts' => 'integer',
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subscription($relation = false)
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id', $relation);
    }

    public function issueDelivery($relation = false)
    {
        return $this->belongsTo(IssueDelivery::class, 'issue_delivery_id', 'id', $relation);
    }

    public function isScheduled(): bool
    {
        return $this->status === IssueDeliveredStatus::SCHEDULED;
    }

    public function isDelivered(): bool
    {
        return $this->status === IssueDeliveredStatus::DELIVERED;
    }

    public function canRetry(int $maxAttempts = 3): bool
    {
        return $this->isFailed() && $this->attempts < $maxAttempts;
    }

    public function isFailed(): bool
    {
        return $this->status === IssueDeliveredStatus::FAILED;
    }

    public function markAsDelivered(?\DateTime $deliveredAt = null): void
    {
        $this->update([
            'status' => IssueDeliveredStatus::DELIVERED->value,
            'delivered_at' => ($deliveredAt ?? new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => IssueDeliveredStatus::FAILED->value,
            'attempts' => $this->attempts + 1,
            'failure_reason' => $this->buildFailureLog($reason),
        ]);
    }

    private function buildFailureLog(string $newReason): string
    {
        $timestamp = (new \DateTime())->format('Y-m-d H:i:s');
        $attemptLog = "[{$timestamp}] Attempt {$this->attempts}: {$newReason}";

        if ($this->failure_reason) {
            return $this->failure_reason . "\n" . $attemptLog;
        }

        return $attemptLog;
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', IssueDeliveredStatus::SCHEDULED->value);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', IssueDeliveredStatus::FAILED->value);
    }

    public function scopeCanRetry($query, int $maxAttempts = 3)
    {
        return $query->where('status', IssueDeliveredStatus::FAILED->value)
            ->where('attempts', '<', $maxAttempts);
    }
}