<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionContinuationResolver;
use PHPUnit\Framework\TestCase;

final class SubscriptionContinuationResolverTest extends TestCase
{
    public function test_scheduled_cancellation_maps_to_global_reactivate_action(): void
    {
        $subscription = $this->subscription([
            'id' => 7,
            'status' => 'active',
            'cancel_at_period_end' => true,
            'cancelled_at' => '2026-06-20 10:00:00',
        ]);

        $action = (new SubscriptionContinuationResolver())->resolve($subscription, [
            'key' => 'cancellation_scheduled',
        ]);

        self::assertSame('reactivate', $action['key']);
        self::assertSame('/press-stack/account/subscriptions/7/reactivate', $action['endpoint']);
    }

    public function test_auto_renewing_subscription_never_gets_renew_action(): void
    {
        $subscription = $this->subscription([
            'id' => 8,
            'status' => 'active',
            'auto_renew' => true,
        ]);

        self::assertNull((new SubscriptionContinuationResolver())->resolve($subscription, [
            'key' => 'renewing_soon',
        ]));
    }

    public function test_replaced_subscription_has_no_duplicate_continuation(): void
    {
        self::assertNull((new SubscriptionContinuationResolver())->resolve(
            $this->subscription(['id' => 9, 'status' => 'replaced']),
            ['key' => 'replaced'],
        ));
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
