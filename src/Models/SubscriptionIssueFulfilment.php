<?php

namespace App\Models;

use App\Enums\Subscriptions\SubscriptionIssueFulfilmentStatus;

class SubscriptionIssueFulfilment extends Model
{
    protected $table = 'subscription_issue_fulfilments';

    protected $fillable = [
        'subscription_id',
        'issue_delivery_id',
        'status',
        'attempts',
        'scheduled_for',
        'deferred_until',
        'dispatched_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
        'skip_reason',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'scheduled_for' => 'datetime',
        'deferred_until' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
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
        return $this->status === SubscriptionIssueFulfilmentStatus::SCHEDULED->value;
    }

    public function isDelivered(): bool
    {
        return $this->status === SubscriptionIssueFulfilmentStatus::DELIVERED->value;
    }

    public function isDeferred(): bool
    {
        return $this->deferred_until instanceof \DateTimeInterface
            && $this->deferred_until > new \DateTime();
    }

    public function canDispatchAt(\DateTimeInterface $date): bool
    {
        if (!$this->isScheduled() || $this->dispatched_at instanceof \DateTimeInterface) {
            return false;
        }

        if ($this->scheduled_for instanceof \DateTimeInterface && $this->scheduled_for > $date) {
            return false;
        }

        if ($this->deferred_until instanceof \DateTimeInterface && $this->deferred_until > $date) {
            return false;
        }

        return true;
    }

    public function deferUntil(\DateTimeInterface $date): void
    {
        $this->update(['deferred_until' => $date->format('Y-m-d H:i:s')]);
    }

    public function releaseDeferral(): void
    {
        $this->update(['deferred_until' => null]);
    }

    public function markAsDispatched(?\DateTimeInterface $date = null): void
    {
        $dispatchedAt = $date ?? new \DateTime();

        $this->update([
            'dispatched_at' => $dispatchedAt->format('Y-m-d H:i:s'),
        ]);

        $this->syncSubscriptionFulfilmentCounts();
    }

    public function canRetry(int $maxAttempts = 3): bool
    {
        return $this->isFailed() && $this->attempts < $maxAttempts;
    }

    public function isFailed(): bool
    {
        return $this->status === SubscriptionIssueFulfilmentStatus::FAILED->value;
    }

    public function markAsDelivered(?\DateTime $deliveredAt = null): void
    {
        $this->update([
            'status' => SubscriptionIssueFulfilmentStatus::DELIVERED->value,
            'delivered_at' => ($deliveredAt ?? new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $this->syncSubscriptionFulfilmentCounts();
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => SubscriptionIssueFulfilmentStatus::FAILED->value,
            'attempts' => $this->attempts + 1,
            'failed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'failure_reason' => $this->buildFailureLog($reason),
        ]);

        $this->syncSubscriptionFulfilmentCounts();
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
        return $query->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', SubscriptionIssueFulfilmentStatus::FAILED->value);
    }

    public function scopeRetriable($query, int $maxAttempts = 3)
    {
        return $query->where('status', SubscriptionIssueFulfilmentStatus::FAILED->value)
            ->where('attempts', '<', $maxAttempts);
    }

    private function syncSubscriptionFulfilmentCounts(): void
    {
        if (!$this->subscription_id) {
            return;
        }

        $subscriptionId = (int) $this->subscription_id;

        Subscription::where('id', $subscriptionId)->update([
            'fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)->count(),
            'scheduled_fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
                ->where('status', SubscriptionIssueFulfilmentStatus::SCHEDULED->value)
                ->whereNull('dispatched_at')
                ->count(),
            'dispatched_fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
                ->whereNotNull('dispatched_at')
                ->count(),
            'delivered_fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
                ->where('status', SubscriptionIssueFulfilmentStatus::DELIVERED->value)
                ->count(),
            'failed_fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
                ->where('status', SubscriptionIssueFulfilmentStatus::FAILED->value)
                ->count(),
            'superseded_fulfilments_count' => SubscriptionIssueFulfilment::where('subscription_id', $subscriptionId)
                ->where('status', SubscriptionIssueFulfilmentStatus::SUPERSEDED->value)
                ->count(),
        ]);
    }
}
