<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;

class SubscriptionRenewalTracker
{
    public function recordRenewal(Subscription $subscription): void
    {
        $now = now_datetime();

        $subscription->renewal_count = ((int)($subscription->renewal_count ?? 0)) + 1;

        if ($subscription->first_renewed_at === null) {
            $subscription->first_renewed_at = $now;
        }

        $subscription->last_renewed_at = $now;
        $subscription->save();
    }

    public function recordRenewalReplacement(
        Subscription $oldSubscription,
        Subscription $newSubscription,
    ): void {
        $now = now_datetime();
        $nextRenewalCount = ((int)($oldSubscription->renewal_count ?? 0)) + 1;
        $firstRenewedAt = $oldSubscription->first_renewed_at ?? $now;

        $oldSubscription->renewal_count = $nextRenewalCount;
        $oldSubscription->first_renewed_at = $firstRenewedAt;
        $oldSubscription->last_renewed_at = $now;
        $oldSubscription->save();

        $newSubscription->renewal_count = $nextRenewalCount;
        $newSubscription->first_renewed_at = $firstRenewedAt;
        $newSubscription->last_renewed_at = $now;
        $newSubscription->save();
    }
}
