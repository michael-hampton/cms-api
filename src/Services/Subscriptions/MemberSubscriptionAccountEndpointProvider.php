<?php

namespace App\Services\Subscriptions;

final readonly class MemberSubscriptionAccountEndpointProvider implements SubscriptionAccountEndpointProviderInterface
{
    public function __construct(private string $siteSlug) {}

    public function forId(int $subscriptionId): array
    {
        $base = '/' . $this->siteSlug . '/member/subscriptions/unified/' . $subscriptionId;

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
            'pause_endpoint' => $base . '/pause',
            'resume_endpoint' => $base . '/resume',
            'cancel_endpoint' => $base . '/cancel',
            'reactivate_endpoint' => $base . '/reactivate',
            'renew_url' => $base . '/renew',
            'resubscribe_endpoint' => $base . '/resubscribe',
            'settle_payment_url' => $base . '/settle-payment',
        ];
    }
}
