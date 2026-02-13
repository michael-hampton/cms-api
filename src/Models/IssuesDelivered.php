<?php

namespace App\Models;

use App\Enums\Subscriptions\IssueDeliveryStatus;

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
        'status' => IssueDeliveryStatus::class,
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
        return $this->status === IssueDeliveryStatus::SCHEDULED;
    }

    public function isDelivered(): bool
    {
        return $this->status === IssueDeliveryStatus::DELIVERED;
    }

    public function canRetry(int $maxAttempts = 3): bool
    {
        return $this->isFailed() && $this->attempts < $maxAttempts;
    }

    public function isFailed(): bool
    {
        return $this->status === IssueDeliveryStatus::FAILED;
    }

    public function markAsDelivered(?\DateTime $deliveredAt = null): void
    {
        $this->update([
            'status' => IssueDeliveryStatus::DELIVERED->value,
            'delivered_at' => ($deliveredAt ?? new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => IssueDeliveryStatus::FAILED->value,
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
        return $query->where('status', IssueDeliveryStatus::SCHEDULED->value);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', IssueDeliveryStatus::FAILED->value);
    }

    public function scopeCanRetry($query, int $maxAttempts = 3)
    {
        return $query->where('status', IssueDeliveryStatus::FAILED->value)
            ->where('attempts', '<', $maxAttempts);
    }
}