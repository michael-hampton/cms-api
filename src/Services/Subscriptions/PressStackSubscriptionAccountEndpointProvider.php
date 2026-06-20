<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;

final class PressStackSubscriptionAccountEndpointProvider implements SubscriptionAccountEndpointProviderInterface
{
    public function for(Subscription $subscription): array
    {
        $base = '/press-stack/account/subscriptions/' . $subscription->id;

        return [
            'auto_renew_endpoint' => $base . '/auto-renew',
            'billing_date_preview_endpoint' => $base . '/billing-date/preview',
            'billing_date_update_endpoint' => $base . '/billing-date',
            'history_endpoint' => $base . '/history',
            'preference_endpoint' => $base . '/preferences',
            'delivery_status_endpoint' => $base . '/delivery',
            'issue_delivery_endpoint' => $base . '/issue-deliveries',
        ];
    }
}
