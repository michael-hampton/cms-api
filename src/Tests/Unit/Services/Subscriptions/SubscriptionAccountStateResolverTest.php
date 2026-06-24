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
            'end_date' => '+5 days',
        ]));

        self::assertSame('suspended', $state['key']);
        self::assertSame('action_required', $state['group']);
    }

    public function test_suspended_payment_failure_uses_backend_suspension_date_and_recoverable_code(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'suspended',
            'suspension_code' => 'payment_failure',
            'suspended_at' => '2026-06-12',
            'next_billing_date' => '2026-06-10',
        ]));

        self::assertSame('suspended', $state['key']);
        self::assertSame('Suspended', $state['label']);
        self::assertSame('Suspended on', $state['date_label']);
        self::assertSame('12 Jun 2026', $state['date_value']);
        self::assertSame('payment_failure', $state['suspension_code']);
        self::assertTrue($state['is_recoverable']);
    }

    public function test_fraud_suspension_is_not_recoverable(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'suspended',
            'suspension_code' => 'fraud_hold',
            'suspended_at' => '2026-06-12',
        ]));

        self::assertSame('suspended', $state['key']);
        self::assertSame('fraud_hold', $state['suspension_code']);
        self::assertFalse($state['is_recoverable']);
        self::assertStringContainsString('support review', $state['copy']);
    }

    public function test_unknown_suspension_code_fails_safely(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'suspended',
            'suspension_code' => 'manual_platform_hold',
        ]));

        self::assertSame('suspended', $state['key']);
        self::assertSame('manual_platform_hold', $state['suspension_code']);
        self::assertFalse($state['is_recoverable']);
        self::assertStringContainsString('Manage your subscription', $state['copy']);
    }

    public function test_scheduled_cancellation_remains_current_until_end_date(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'active',
            'auto_renew' => false,
            'cancel_at_period_end' => true,
            'end_date' => '+20 days',
        ]));

        self::assertSame('cancellation_scheduled', $state['key']);
        self::assertSame('current', $state['group']);
    }

    public function test_expired_subscription_is_previous(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'expired',
            'end_date' => '-1 day',
        ]));

        self::assertSame('expired', $state['key']);
        self::assertSame('previous', $state['group']);
    }

    public function test_expiring_soon_uses_authoritative_subscription_segment(): void
    {
        $state = $this->resolver->resolve(
            $this->subscription([
                'status' => 'active',
                'auto_renew' => false,
                'end_date' => '2026-07-01',
            ]),
            new DateTimeImmutable('2026-06-20'),
            'renewal_due_30_days',
        );

        self::assertSame('expiring_soon', $state['key']);
        self::assertSame('1 Jul 2026', $state['date_value']);
    }

    public function test_renewing_soon_uses_authoritative_subscription_segment(): void
    {
        $state = $this->resolver->resolve(
            $this->subscription([
                'status' => 'active',
                'auto_renew' => true,
                'next_billing_date' => '2026-07-01',
            ]),
            new DateTimeImmutable('2026-06-20'),
            'renewal_due_30_days',
        );

        self::assertSame('renewing_soon', $state['key']);
        self::assertSame('1 Jul 2026', $state['date_value']);
    }

    public function test_active_status_is_not_promoted_from_dates_without_segment(): void
    {
        $state = $this->resolver->resolve(
            $this->subscription([
                'status' => 'active',
                'auto_renew' => false,
                'end_date' => '2026-06-21',
            ]),
            new DateTimeImmutable('2026-06-20'),
        );

        self::assertSame('active', $state['key']);
    }

    public function test_paused_subscription_uses_existing_domain_state(): void
    {
        $state = $this->resolver->resolve(
            $this->subscription([
                'status' => 'paused',
                'pause_until' => '2026-07-20',
            ]),
            new DateTimeImmutable('2026-06-20 12:00:00'),
        );

        self::assertSame('paused', $state['key']);
        self::assertSame('20 Jul 2026', $state['date_value']);
    }

    public function test_processing_is_never_presented_as_active(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'processing',
            'end_date' => '+1 year',
        ]));

        self::assertSame('processing', $state['key']);
        self::assertSame('action_required', $state['group']);
    }

    public function test_replaced_takes_precedence_over_past_end_date(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'replaced',
            'end_date' => '-1 day',
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
