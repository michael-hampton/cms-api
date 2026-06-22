<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Services\Subscriptions\MemberSubscriptionAccountEndpointProvider;
use App\Services\Subscriptions\PressStackSubscriptionAccountEndpointProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SubscriptionAccountEndpointProviderTest extends TestCase
{
    #[DataProvider('providerCases')]
    public function test_complete_endpoint_contract(object $provider, string $base): void
    {
        $endpoints = $provider->forId(42);

        self::assertSame([
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
            'resubscribe_url' => $base . '/resubscribe',
            'resubscribe_endpoint' => $base . '/resubscribe',
            'settle_payment_url' => $base . '/settle-payment',
        ], $endpoints);
    }

    public static function providerCases(): array
    {
        return [
            'press stack' => [
                new PressStackSubscriptionAccountEndpointProvider(),
                '/press-stack/account/subscriptions/42',
            ],
            'member' => [
                new MemberSubscriptionAccountEndpointProvider('daily-news'),
                '/daily-news/member/subscriptions/unified/42',
            ],
        ];
    }
}
