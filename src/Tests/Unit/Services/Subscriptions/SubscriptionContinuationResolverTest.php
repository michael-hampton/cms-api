<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Support\SiteContext;
use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionContinuationResolver;
use DateTime;
use PHPUnit\Framework\TestCase;

final class SubscriptionContinuationResolverTest extends TestCase
{
    private SubscriptionContinuationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SubscriptionContinuationResolver();
    }

    public function test_scheduled_cancellation_resolves_to_reactivate(): void
    {
        $subscription = $this->subscription();
        $subscription->cancel_at_period_end = true;
        $subscription->end_date = new DateTime('+10 days');

        $action = $this->resolver->resolve($subscription, ['key' => 'cancellation_scheduled']);

        $this->assertSame('reactivate', $action['key']);
        $this->assertSame('api', $action['type']);
        $this->assertSame('POST', $action['method']);
    }

    public function test_expiring_non_renewing_subscription_resolves_to_renew(): void
    {
        $subscription = $this->subscription();
        $subscription->auto_renew = false;

        $action = $this->resolver->resolve($subscription, ['key' => 'expiring_soon']);

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
        $subscription = $this->subscription();
        $subscription->auto_renew = true;

        $this->assertNull($this->resolver->resolve($subscription, ['key' => 'active']));
    }

    public function test_replaced_subscription_has_no_continuation_action(): void
    {
        $this->assertNull($this->resolver->resolve($this->subscription(), ['key' => 'replaced']));
    }

    private function subscription(): Subscription
    {
        $subscription = new Subscription();
        $subscription->id = 42;
        $subscription->status = 'active';
        $subscription->auto_renew = false;
        $subscription->cancel_at_period_end = false;
        $subscription->end_date = new DateTime('+1 year');

        return $subscription;
    }
}
