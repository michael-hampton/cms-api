<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionAccountStateResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;

final class SubscriptionAccountStateResolverTest extends FunctionalTestCase
{
    private SubscriptionAccountStateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SubscriptionAccountStateResolver();
    }

    public function test_payment_failure_takes_precedence_over_other_states(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'past_due',
            'auto_renew' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+5 days')),
        ]));

        self::assertSame('suspended', $state['key']);
        self::assertSame('action_required', $state['group']);
        self::assertSame('danger', $state['tone']);
    }

    public function test_scheduled_cancellation_remains_current_until_end_date(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'active',
            'auto_renew' => false,
            'cancel_at_period_end' => true,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+20 days')),
        ]));

        self::assertSame('cancellation_scheduled', $state['key']);
        self::assertSame('current', $state['group']);
    }

    public function test_expired_subscription_is_previous(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'expired',
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]));

        self::assertSame('expired', $state['key']);
        self::assertSame('previous', $state['group']);
    }

    public function test_fixed_term_subscription_near_end_is_expiring_soon(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'active',
            'auto_renew' => false,
            'end_date' => date('Y-m-d H:i:s', strtotime('+10 days')),
        ]));

        self::assertSame('expiring_soon', $state['key']);
        self::assertSame('warning', $state['tone']);
    }

    private function subscription(array $attributes): Subscription
    {
        $subscription = new Subscription();
        foreach ($attributes as $key => $value) {
            $subscription->{$key} = $value;
        }

        return $subscription;
    }
}
