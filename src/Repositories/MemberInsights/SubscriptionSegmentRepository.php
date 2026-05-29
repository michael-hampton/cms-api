<?php

namespace App\Repositories\MemberInsights;

use App\Enums\Member\SubscriptionSegmentStatus;
use App\Models\Model;
use App\Models\SubscriptionSegment;
use App\Repositories\Repository;

class SubscriptionSegmentRepository extends Repository
{
    /**
     * Returns the single active segment for a subscription, or null.
     */
    public function findActive(int $subscriptionId): ?SubscriptionSegment
    {
        return SubscriptionSegment::with('segment')
            ->where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionSegmentStatus::Active->value)
            ->latest('id')
            ->first();
    }

    /**
     * Returns all assignments for a subscription ordered newest first.
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
    ): Model {
        return SubscriptionSegment::create([
            'subscription_id' => $subscriptionId,
            'segment_id'      => $segmentId,
            'assigned_at'     => $assignedAt,
            'evaluated_at'    => $assignedAt,
            'status'          => SubscriptionSegmentStatus::Active->value,
            'metadata'        => $metadata ?: null,
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

    protected function getModelClass(): string
    {
        return SubscriptionSegment::class;
    }
}