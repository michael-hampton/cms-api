<?php

namespace App\Services\Subscriptions;

final class PressStackSubscriptionAccountEndpointProvider implements SubscriptionAccountEndpointProviderInterface
{
    public function forId(int $subscriptionId): array
    {
        $base = '/press-stack/account/subscriptions/' . $subscriptionId;

        return [
            'auto_renew_endpoint' => $base . '/auto-renew',
            'renewal_offer_endpoint' => $base . '/renewal-offer',
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
            'resubscribe_url' => $base . '/resubscribe',
            'settle_payment_url' => $base . '/settle-payment',
            'payment_method_endpoint' => $base . '/payment-method',
            'payment_methods_list_endpoint' => '/press-stack/account/billing/payment-methods',
            'payment_methods_page_url' => '/press-stack/account/payment-methods',
        ];
    }
}