<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionPauseFlowProvider;
use PHPUnit\Framework\TestCase;

final class SubscriptionPauseFlowProviderTest extends TestCase
{
    private SubscriptionPauseFlowProvider $provider;

    public function test_print_subscription_explains_delivery_continues_separately(): void
    {
        $flow = $this->provider->for(
            $this->subscription('print', true, true),
            '/press-stack/account/subscriptions/42/pause',
        );

        self::assertSame('Pause subscription', $flow['title']);
        self::assertStringContainsString('Print deliveries', $flow['delivery_copy']);
        self::assertStringContainsString('Pause print delivery', $flow['delivery_copy']);
        self::assertStringContainsString('not changed', $flow['fulfilment_copy']);
        self::assertSame('/press-stack/account/subscriptions/42/pause', $flow['endpoint']);
    }

    public function test_digital_subscription_explains_access_stops_and_has_no_print_delivery(): void
    {
        $flow = $this->provider->for(
            $this->subscription('digital', true, true),
            '/daily-news/member/subscriptions/unified/42/pause',
        );

        self::assertStringContainsString('Digital', $flow['access_copy']);
        self::assertStringContainsString('will stop', $flow['access_copy']);
        self::assertStringContainsString('no print deliveries', $flow['delivery_copy']);
        self::assertSame('/daily-news/member/subscriptions/unified/42/pause', $flow['endpoint']);
    }

    public function test_renewal_copy_matches_current_preference(): void
    {
        $enabled = $this->provider->for($this->subscription('digital', true, true), '/pause');
        $disabled = $this->provider->for($this->subscription('digital', false, true), '/pause');

        self::assertStringContainsString('restored', $enabled['renewal_copy']);
        self::assertStringContainsString('remain disabled', $disabled['renewal_copy']);
    }

    public function test_flow_is_unavailable_for_unsupported_states(): void
    {
        foreach (['paused', 'expired', 'cancelled', 'past_due'] as $status) {
            self::assertNull($this->provider->for(
                $this->subscription('digital', true, true, $status),
                '/pause',
            ));
        }
    }

    public function test_stripe_backed_subscription_keeps_pause_flow(): void
    {
        $subscription = $this->subscription('digital', true, true);
        $subscription->setAttribute('payment_subscription_id', 'sub_123');

        $flow = $this->provider->for($subscription, '/pause');

        self::assertNotNull($flow);
        self::assertSame('/pause', $flow['endpoint']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new SubscriptionPauseFlowProvider();
    }

    private function subscription(
        string $deliveryType,
        bool $autoRenew,
        bool $includesDigitalAccess,
        string $status = 'active',
    ): Subscription {
        $subscription = $this->getMockBuilder(Subscription::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $subscription->setAttribute('id', 42);
        $subscription->setAttribute('status', $status);
        $subscription->setAttribute('delivery_type', $deliveryType);
        $subscription->setAttribute('auto_renew', $autoRenew);
        $subscription->setAttribute('includes_digital_access', $includesDigitalAccess);
        $subscription->setAttribute('payment_subscription_id', null);
        $subscription->setAttribute('cancel_at_period_end', false);
        $subscription->setAttribute('start_date', date('Y-m-d H:i:s', strtotime('-1 month')));
        $subscription->setAttribute('end_date', date('Y-m-d H:i:s', strtotime('+6 months')));

        return $subscription;
    }
}
