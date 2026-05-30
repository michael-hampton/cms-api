<?php

namespace App\Repositories\MemberInsights;

use App\Enums\Member\SubscriptionSegmentSource;
use App\Enums\Member\SubscriptionSegmentStatus;
use App\Models\Model;
use App\Models\SubscriptionSegment;
use App\Repositories\Repository;

class SubscriptionSegmentRepository extends Repository
{
    /**
     * Returns the single active segment for a subscription, or null.
     * Active manual overrides are returned in preference to rule-based assignments.
     */
    public function findActive(int $subscriptionId): ?SubscriptionSegment
    {
        return SubscriptionSegment::with('segment')
            ->where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionSegmentStatus::Active->value)
            ->latest('assigned_at')
            ->first();
    }

    /**
     * Returns all assignments for a subscription, newest first.
     */
    public function getHistory(int $subscriptionId): \App\Framework\Support\Collection
    {
        return SubscriptionSegment::with('segment')
            ->where('subscription_id', $subscriptionId)
            ->orderByDesc('assigned_at')
            ->get();
    }

    public function createSubscriptionSegment(
        int $subscriptionId,
        int $segmentId,
        \DateTimeInterface $assignedAt,
        array $metadata = [],
        string $source = 'rule_based',
    ): Model {
        return SubscriptionSegment::create([
            'subscription_id' => $subscriptionId,
            'segment_id'      => $segmentId,
            'assigned_at'     => $assignedAt,
            'evaluated_at'    => $assignedAt,
            'status'          => SubscriptionSegmentStatus::Active->value,
            'source'          => $source,
            'metadata'        => $metadata ?: null,
        ]);
    }

    public function createManual(
        int $subscriptionId,
        int $segmentId,
        \DateTimeInterface $assignedAt,
        string $reason,
        ?int $assignedByUserId,
        ?\DateTimeInterface $expiresAt = null,
    ): Model {
        return SubscriptionSegment::create([
            'subscription_id'      => $subscriptionId,
            'segment_id'           => $segmentId,
            'assigned_at'          => $assignedAt,
            'evaluated_at'         => $assignedAt,
            'status'               => SubscriptionSegmentStatus::Active->value,
            'source'               => SubscriptionSegmentSource::Manual->value,
            'reason'               => $reason,
            'expires_at'           => $expiresAt,
            'assigned_by_user_id'  => $assignedByUserId,
        ]);
    }

    /**
     * Marks all currently active assignments for the subscription as 'replaced'.
     */
    public function replaceActive(int $subscriptionId): void
    {
        SubscriptionSegment::where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionSegmentStatus::Active->value)
            ->update(['status' => SubscriptionSegmentStatus::Replaced->value]);
    }

    /**
     * Returns true if the subscription has an unexpired active manual override.
     */
    public function hasActiveManualOverride(int $subscriptionId): bool
    {
        return SubscriptionSegment::where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionSegmentStatus::Active->value)
            ->where('source', SubscriptionSegmentSource::Manual->value)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now_datetime());
            })
            ->exists();
    }

    protected function getModelClass(): string
    {
        return SubscriptionSegment::class;
    }
}