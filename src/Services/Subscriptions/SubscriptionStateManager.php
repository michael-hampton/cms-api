<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;

class SubscriptionStateManager
{
    public function markActiveFromStripe(
        Subscription $subscription,
        ?int         $currentPeriodStart,
        ?int         $currentPeriodEnd,
    ): void
    {
        $update = [
            'status' => 'active',
        ];

        if ($currentPeriodStart !== null) {
            $update['current_period_start'] = date('Y-m-d H:i:s', $currentPeriodStart);
        }

        if ($currentPeriodEnd !== null) {
            $update['current_period_end'] = date('Y-m-d H:i:s', $currentPeriodEnd);
        }

        $subscription->update($update);
    }
}
