<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionCancellationFlowProvider;
use PHPUnit\Framework\TestCase;

final class SubscriptionCancellationFlowProviderTest extends TestCase
{
    public function test_flow_exposes_complete_backend_data_and_global_endpoint(): void
    {
        $subscription = $this->subscription([
            'id' => 12,
            'status' => 'active',
            'end_date' => '2026-07-20 00:00:00',
        ]);

        $flow = (new SubscriptionCancellationFlowProvider())->for($subscription);

        self::assertSame('/press-stack/account/subscriptions/12/cancel', $flow['endpoint']);
        self::assertArrayHasKey('review_copy', $flow);
        self::assertArrayHasKey('access_message', $flow);
        self::assertArrayHasKey('billing_message', $flow);
        self::assertArrayHasKey('refund_message', $flow);
        self::assertNotEmpty($flow['lost_benefits']);
        self::assertNotEmpty($flow['reasons']);
        self::assertArrayHasKey('confirmation', $flow);
    }

    public function test_scheduled_or_terminal_subscription_is_not_cancellable(): void
    {
        $provider = new SubscriptionCancellationFlowProvider();

        self::assertNull($provider->for($this->subscription([
            'status' => 'active',
            'cancel_at_period_end' => true,
            'cancelled_at' => '2026-06-20 10:00:00',
        ])));
        self::assertNull($provider->for($this->subscription(['status' => 'expired'])));
    }

    private function subscription(array $attributes): Subscription
    {
        $subscription = $this->getMockBuilder(Subscription::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        foreach ($attributes as $key => $value) {
            $subscription->setAttribute($key, $value);
        }
        return $subscription;
    }
}
