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
            'upgrade_options_endpoint' => $base . '/upgrades',
            'upgrade_preview_endpoint' => $base . '/upgrades/preview',
            'upgrade_endpoint' => $base . '/upgrades',
            'preference_endpoint' => $base . '/preferences',
            'delivery_status_endpoint' => $base . '/delivery',
            'delivery_pause_endpoint' => $base . '/delivery/pause',
            'delivery_resume_endpoint' => $base . '/delivery/resume',
            'delivery_address_endpoint' => $base . '/delivery-addresses',
            'delivery_address_update_endpoint' => $base . '/delivery-addresses/__ADDRESS_ID__/default',
            'issue_delivery_endpoint' => $base . '/issue-deliveries',
        ];
    }
}
