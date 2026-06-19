<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionContinuationResolver;
use App\Tests\Support\MocksSubscriptionModels;
use DateTime;
use PHPUnit\Framework\TestCase;

final class SubscriptionContinuationResolverTest extends TestCase
{
    use MocksSubscriptionModels;

    private SubscriptionContinuationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SubscriptionContinuationResolver();
    }

    public function test_scheduled_cancellation_resolves_to_reactivate(): void
    {
        $action = $this->resolver->resolve($this->subscription([
            'cancel_at_period_end' => true,
            'end_date' => new DateTime('+10 days'),
        ]), ['key' => 'cancellation_scheduled']);

        $this->assertSame('reactivate', $action['key']);
        $this->assertSame('api', $action['type']);
        $this->assertSame('POST', $action['method']);
    }

    public function test_expiring_non_renewing_subscription_resolves_to_renew(): void
    {
        $action = $this->resolver->resolve(
            $this->subscription(['auto_renew' => false]),
            ['key' => 'expiring_soon']
        );

        $this->assertSame('renew', $action['key']);
        $this->assertSame('redirect', $action['type']);
    }

    public function test_expired_and_cancelled_terminal_subscriptions_resolve_to_resubscribe(): void
    {
        foreach (['expired', 'cancelled'] as $stateKey) {
            $action = $this->resolver->resolve($this->subscription(), ['key' => $stateKey]);
            $this->assertSame('resubscribe', $action['key']);
        }
    }

    public function test_active_auto_renewing_subscription_has_no_continuation_action(): void
    {
        $this->assertNull($this->resolver->resolve(
            $this->subscription(['auto_renew' => true]),
            ['key' => 'active']
        ));
    }

    public function test_replaced_subscription_has_no_continuation_action(): void
    {
        $this->assertNull($this->resolver->resolve($this->subscription(), ['key' => 'replaced']));
    }

    private function subscription(array $attributes = []): Subscription
    {
        return $this->mockSubscription(array_merge([
            'id' => 42,
            'status' => 'active',
            'auto_renew' => false,
            'cancel_at_period_end' => false,
            'end_date' => new DateTime('+1 year'),
        ], $attributes));
    }
}
