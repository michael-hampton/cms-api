<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\SubscriptionEvent;
use App\Repositories\Repository;

/**
 * Persistence layer for the subscription_events table.
 *
 * Uses the query builder (DB::table() style) rather than raw SQL,
 * consistent with other repositories in this codebase.
 */
class SubscriptionEventRepository extends Repository
{
    /**
     * Fetch events for a subscription, newest first, with limit/offset pagination.
     *
     * @return object[]
     */
    public function findBySubscription(int $subscriptionId, int $limit, int $offset): Collection
    {
        return SubscriptionEvent::where('subscription_id', $subscriptionId)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public function countBySubscription(int $subscriptionId): int
    {
        return SubscriptionEvent::where('subscription_id', $subscriptionId)
            ->count();
    }

    protected function getModelClass(): string
    {
        return SubscriptionEvent::class;
    }

    private function findOrFail(int $id): object
    {
        $row = SubscriptionEvent::where('id', $id)
            ->first();

        if (!$row) {
            throw new \RuntimeException("SubscriptionEvent #{$id} not found after insert.");
        }

        return $row;
    }
}