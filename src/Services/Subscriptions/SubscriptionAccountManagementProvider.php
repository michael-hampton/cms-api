<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;

final class SubscriptionAccountManagementProvider
{
    public function for(Subscription $subscription, array $displayState): array
    {
        $base = '/press-stack/account/subscriptions/' . $subscription->id;
        $isHistorical = ($displayState['group'] ?? null) === 'previous';
        $isPrint = $subscription->isPrint();

        return [
            'id' => $subscription->id,
            'plan_name' => $subscription->plan_name ?? 'Subscription',
            'status_label' => $displayState['label'] ?? '',
            'type' => $isPrint ? 'print' : 'digital',
            'auto_renew' => (bool) $subscription->auto_renew,
            'can_manage_auto_renew' => !$isHistorical && !$subscription->isExpired(),
            'auto_renew_endpoint' => $base . '/auto-renew',
            'can_manage_billing_date' => $subscription->hasStripeSubscription()
                && $subscription->auto_renew
                && $subscription->status === 'active',
            'billing_day_of_month' => $subscription->billing_day_of_month
                ?? $subscription->next_billing_date?->format('j'),
            'billing_date_preview_endpoint' => $base . '/billing-date/preview',
            'billing_date_update_endpoint' => $base . '/billing-date',
            'history_endpoint' => $base . '/history',
            'can_upgrade' => !$isHistorical && $subscription->isActive(),
            'upgrade_options_endpoint' => $base . '/upgrades',
            'upgrade_preview_endpoint' => $base . '/upgrades/preview',
            'upgrade_endpoint' => $base . '/upgrades',
            'preference_endpoint' => $base . '/preferences',
            'can_manage_delivery' => $isPrint && !$isHistorical,
            'delivery_status_endpoint' => $base . '/delivery',
            'delivery_pause_endpoint' => $base . '/delivery/pause',
            'delivery_resume_endpoint' => $base . '/delivery/resume',
            'delivery_address_endpoint' => $base . '/delivery-addresses',
            'delivery_address_update_endpoint' => $base . '/delivery-addresses/__ADDRESS_ID__/default',
            'issue_delivery_endpoint' => $base . '/issue-deliveries',
            'digital_download_url' => $subscription->download_url ?: null,
        ];
    }
}
