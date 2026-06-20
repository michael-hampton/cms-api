<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionAccountStateResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SubscriptionAccountStateResolverTest extends TestCase
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

    public function test_scheduled_cancellation_takes_precedence_over_paused_state(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'paused',
            'cancel_at_period_end' => true,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'pause_until' => date('Y-m-d H:i:s', strtotime('+10 days')),
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

    public function test_paused_subscription_uses_existing_domain_state(): void
    {
        $now = new DateTimeImmutable('2026-06-20 12:00:00');
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'paused',
            'pause_until' => '2026-07-20',
        ]), $now);

        self::assertSame('paused', $state['key']);
        self::assertSame('current', $state['group']);
        self::assertSame('20 Jul 2026', $state['date_value']);
    }

    public function test_processing_is_never_presented_as_active(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'processing',
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 year')),
        ]));

        self::assertSame('processing', $state['key']);
        self::assertSame('action_required', $state['group']);
    }

    public function test_replaced_takes_precedence_over_past_end_date(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'replaced',
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]));

        self::assertSame('replaced', $state['key']);
        self::assertSame('previous', $state['group']);
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
