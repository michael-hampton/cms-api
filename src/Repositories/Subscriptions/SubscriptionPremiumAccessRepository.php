<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\Subscription;
use App\Models\SubscriptionPremiumAccess;
use App\Repositories\Repository;

class SubscriptionPremiumAccessRepository extends Repository
{
    public function findBySubscription(int $subscriptionId): Collection
    {
        return SubscriptionPremiumAccess::query()
            ->where('subscription_id', $subscriptionId)
            ->orderBy('granted_at', 'desc')
            ->get();
    }

    public function findActiveBySubscription(int $subscriptionId): Collection
    {
        return SubscriptionPremiumAccess::query()
            ->where('subscription_id', $subscriptionId)
            ->active()
            ->orderBy('granted_at', 'desc')
            ->get();
    }

    public function findExisting(
        int    $subscriptionId,
        string $type,
        string $identifier
    ): ?SubscriptionPremiumAccess
    {
        return SubscriptionPremiumAccess::query()
            ->where('subscription_id', $subscriptionId)
            ->where('premium_type', $type)
            ->where('premium_identifier', $identifier)
            ->first();
    }

    public function findSubscription(int $subscriptionId): ?Subscription
    {
        return Subscription::query()->find($subscriptionId);
    }

    public function deactivate(int $subscriptionId, string $type, string $identifier): bool
    {
        return (bool)SubscriptionPremiumAccess::query()
            ->where('subscription_id', $subscriptionId)
            ->where('premium_type', $type)
            ->where('premium_identifier', $identifier)
            ->update(['is_active' => false]);
    }

    public function deactivateAllForSubscription(int $subscriptionId): void
    {
        SubscriptionPremiumAccess::query()
            ->where('subscription_id', $subscriptionId)
            ->update(['is_active' => false]);
    }

    protected function getModelClass(): string
    {
        return SubscriptionPremiumAccess::class;
    }
}