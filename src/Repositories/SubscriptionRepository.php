<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Subscription;

class SubscriptionRepository extends Repository
{
    protected function getModelClass(): string
    {
        return Subscription::class;
    }

    public function getActiveSubscriptionForMember(int $memberId, ?int $siteId = null): ?Subscription
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->first();
    }

    public function getSubscriptionHistory(int $memberId, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? $this->siteId;

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function countActiveSubscriptions(int $memberId, ?int $siteId = null): int
    {
        $siteId = $siteId ?? $this->siteId;

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->count();
    }

    public function cancelSubscription(int $subscriptionId): bool
    {
        $subscription = $this->find($subscriptionId);

        if (!$subscription) {
            return false;
        }

        return $this->update($subscriptionId, [
                'status' => 'cancelled',
                'auto_renew' => false
            ]) !== null;
    }
}