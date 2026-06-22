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

        if ($displayState['key'] === 'expired') {
            return [
                'key' => 'reactivate',
                'label' => 'Reactivate',
                'type' => 'modal',
                'modal' => 'subscription_checkout',
                'plan_id' => (int) $subscription->plan_id,
                'plan_slug' => (string) ($subscription->plan?->slug ?? ''),
                'subscription_id' => (int) $subscription->id,
                'tone' => 'commercial',
            ];
        }

        if ($displayState['key'] === 'cancelled') {
            return [
                'key' => 'resubscribe',
                'label' => 'Resubscribe',
                'type' => 'modal',
                'modal' => 'subscription_checkout',
                'plan_id' => (int) $subscription->plan_id,
                'plan_slug' => (string) ($subscription->plan?->slug ?? ''),
                'subscription_id' => (int) $subscription->id,
                'tone' => 'commercial',
            ];
        }

        return null;
    }
}
