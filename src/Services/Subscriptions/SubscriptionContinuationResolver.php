<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;

final class SubscriptionContinuationResolver
{
    public function resolve(Subscription $subscription, array $displayState): ?array
    {
        if ($subscription->isCancellationScheduled()) {
            return [
                'key' => 'reactivate',
                'label' => 'Reactivate',
                'type' => 'api',
                'method' => 'POST',
                'endpoint' => "/press-stack/account/subscriptions/{$subscription->id}/reactivate",
                'tone' => 'commercial',
            ];
        }

        if ($displayState['key'] === 'expiring_soon' && !$subscription->auto_renew) {
            return [
                'key' => 'renew',
                'label' => 'Renew',
                'type' => 'redirect',
                'url' => "/press-stack/account/subscriptions/{$subscription->id}/renew",
                'tone' => 'commercial',
            ];
        }

        if (in_array($displayState['key'], ['expired', 'cancelled'], true)) {
            return [
                'key' => 'resubscribe',
                'label' => 'Resubscribe',
                'type' => 'redirect',
                'url' => "/press-stack/account/subscriptions/{$subscription->id}/resubscribe",
                'tone' => 'commercial',
            ];
        }

        return null;
    }
}
