<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Subscription;

class SubscriptionRenewalTracker
{
    public function __construct(
        private readonly Database $database,
    ) {
    }

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

    /**
     * Records a renewal on both the old (renewed-from) and new (replacement)
     * subscription rows so their renewal_count/first_renewed_at/last_renewed_at
     * stay in sync. Wrapped in its own transaction (nested safely via
     * savepoint if a caller already has one open) so the two saves can never
     * diverge on partial failure, regardless of what the caller does.
     */
    public function recordRenewalReplacement(
        Subscription $oldSubscription,
        Subscription $newSubscription,
    ): void {
        $now = now_datetime();
        $nextRenewalCount = ((int)($oldSubscription->renewal_count ?? 0)) + 1;
        $firstRenewedAt = $oldSubscription->first_renewed_at ?? $now;

        $this->database->transaction(function () use (
            $oldSubscription,
            $newSubscription,
            $now,
            $nextRenewalCount,
            $firstRenewedAt,
        ) {
            $oldSubscription->renewal_count = $nextRenewalCount;
            $oldSubscription->first_renewed_at = $firstRenewedAt;
            $oldSubscription->last_renewed_at = $now;
            $oldSubscription->save();

            $newSubscription->renewal_count = $nextRenewalCount;
            $newSubscription->first_renewed_at = $firstRenewedAt;
            $newSubscription->last_renewed_at = $now;
            $newSubscription->save();

            return true;
        });
    }
}
