<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;

final class SubscriptionAccountManagementProvider
{
    private SubscriptionAccountEndpointProviderInterface $endpoints;

    public function __construct(?SubscriptionAccountEndpointProviderInterface $endpoints = null)
    {
        $this->endpoints = $endpoints ?: new PressStackSubscriptionAccountEndpointProvider();
    }

    public function for(Subscription $subscription, array $displayState): array
    {
        $isHistorical = ($displayState['group'] ?? null) === 'previous';
        $isPrint = $subscription->isPrint();

        $management = [
            'id' => $subscription->id,
            'plan_name' => $subscription->plan_name ?? 'Subscription',
            'status_label' => $displayState['label'] ?? '',
            'type' => $isPrint ? 'print' : 'digital',
            'auto_renew' => (bool) $subscription->auto_renew,
            'can_manage_auto_renew' => !$isHistorical && !$subscription->isExpired(),
            'can_manage_billing_date' => $subscription->hasStripeSubscription()
                && $subscription->auto_renew
                && $subscription->status === 'active',
            'billing_day_of_month' => $subscription->billing_day_of_month
                ?? $subscription->next_billing_date?->format('j'),
            'can_upgrade' => !$isHistorical && $subscription->isActive(),
            'can_manage_delivery' => $isPrint && !$isHistorical,
            'digital_download_url' => $subscription->download_url ?: null,
        ];

        return array_merge($management, $this->endpoints->for($subscription));
    }
}
