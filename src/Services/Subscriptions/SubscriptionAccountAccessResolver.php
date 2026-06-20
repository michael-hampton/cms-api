<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;

final class SubscriptionAccountAccessResolver
{
    public function resolve(int $subscriptionId, int $memberId, ?int $siteId): ?Subscription
    {
        $query = Subscription::where('id', $subscriptionId)
            ->where('member_id', $memberId);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->first();
    }
}
