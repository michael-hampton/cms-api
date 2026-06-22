<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;

final class SubscriptionAccountManagementProvider
{
    private SubscriptionAccountEndpointProviderInterface $endpoints;
    private SubscriptionPauseFlowProvider $pauseFlowProvider;

    public function __construct(
        ?SubscriptionAccountEndpointProviderInterface $endpoints = null,
        ?SubscriptionPauseFlowProvider $pauseFlowProvider = null,
    ) {
        $this->endpoints = $endpoints ?: new PressStackSubscriptionAccountEndpointProvider();
        $this->pauseFlowProvider = $pauseFlowProvider ?: new SubscriptionPauseFlowProvider();
    }

    public function for(Subscription $subscription, array $displayState): array
    {
        $isHistorical = ($displayState['group'] ?? null) === 'previous';
        $isPrint = $subscription->isPrint();
        $endpoints = $this->endpoints->forId((int) $subscription->id);

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
            'pause_flow' => $this->pauseFlowProvider->for(
                $subscription,
                $endpoints['pause_endpoint'],
            ),
        ];

        return array_merge($management, $endpoints);
    }
}
