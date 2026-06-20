<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionAccountManagementProvider;
use PHPUnit\Framework\TestCase;

final class SubscriptionAccountManagementProviderTest extends TestCase
{
    private SubscriptionAccountManagementProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new SubscriptionAccountManagementProvider();
    }

    public function test_active_print_subscription_exposes_print_management_capabilities(): void
    {
        $subscription = $this->subscription([
            'id' => 42,
            'status' => 'active',
            'delivery_type' => 'print',
            'auto_renew' => true,
            'stripe_subscription_id' => 'sub_123',
            'billing_day_of_month' => 18,
        ]);

        $payload = $this->provider->for($subscription, $this->state('current'));

        $this->assertSame(42, $payload['id']);
        $this->assertSame('print', $payload['type']);
        $this->assertTrue($payload['can_manage_auto_renew']);
        $this->assertTrue($payload['can_manage_billing_date']);
        $this->assertTrue($payload['can_manage_delivery']);
        $this->assertTrue($payload['can_upgrade']);
        $this->assertSame(18, $payload['billing_day_of_month']);
        $this->assertSame('/press-stack/account/subscriptions/42/delivery-addresses', $payload['delivery_address_endpoint']);
        $this->assertSame('/press-stack/account/subscriptions/42/issue-deliveries', $payload['issue_delivery_endpoint']);
    }

    public function test_digital_subscription_does_not_expose_print_delivery_management(): void
    {
        $subscription = $this->subscription([
            'id' => 7,
            'status' => 'active',
            'delivery_type' => 'digital',
            'download_url' => '/downloads/edition.pdf',
        ]);

        $payload = $this->provider->for($subscription, $this->state('current'));

        $this->assertSame('digital', $payload['type']);
        $this->assertFalse($payload['can_manage_delivery']);
        $this->assertSame('/downloads/edition.pdf', $payload['digital_download_url']);
    }

    public function test_subscription_without_stripe_cannot_manage_billing_date(): void
    {
        $subscription = $this->subscription([
            'id' => 8,
            'status' => 'active',
            'delivery_type' => 'digital',
            'auto_renew' => true,
            'stripe_subscription_id' => null,
            'payment_subscription_id' => null,
        ]);

        $payload = $this->provider->for($subscription, $this->state('current'));

        $this->assertFalse($payload['can_manage_billing_date']);
    }

    public function test_historical_subscription_disables_mutating_management_capabilities(): void
    {
        $subscription = $this->subscription([
            'id' => 9,
            'status' => 'expired',
            'delivery_type' => 'print',
            'auto_renew' => false,
        ]);

        $payload = $this->provider->for($subscription, $this->state('previous'));

        $this->assertFalse($payload['can_manage_auto_renew']);
        $this->assertFalse($payload['can_manage_delivery']);
        $this->assertFalse($payload['can_upgrade']);
    }

    public function test_missing_download_url_is_represented_as_null(): void
    {
        $subscription = $this->subscription([
            'id' => 10,
            'status' => 'active',
            'delivery_type' => 'digital',
            'download_url' => null,
        ]);

        $payload = $this->provider->for($subscription, $this->state('current'));

        $this->assertNull($payload['digital_download_url']);
    }

    private function subscription(array $attributes): Subscription
    {
        return new Subscription(array_merge([
            'plan_name' => 'Test subscription',
            'status' => 'active',
            'delivery_type' => 'digital',
            'auto_renew' => false,
        ], $attributes));
    }

    private function state(string $group): array
    {
        return [
            'group' => $group,
            'label' => ucfirst($group),
        ];
    }
}
