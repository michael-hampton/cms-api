<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionAccountStateResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SubscriptionAccountStateResolverTest extends TestCase
{
    private SubscriptionAccountStateResolver $resolver;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SubscriptionAccountStateResolver();
        $this->now = new DateTimeImmutable('2026-06-19 12:00:00');
    }

    public function test_suspended_and_past_due_states_take_precedence(): void
    {
        foreach (['suspended', 'past_due', 'unpaid', 'failed'] as $status) {
            $state = $this->resolver->resolve($this->subscription($status), $this->now);

            $this->assertSame('suspended', $state['key']);
            $this->assertSame('action_required', $state['group']);
        }
    }

    public function test_processing_states_are_action_required(): void
    {
        foreach (['incomplete', 'retrying', 'pending'] as $status) {
            $state = $this->resolver->resolve($this->subscription($status), $this->now);

            $this->assertSame('processing', $state['key']);
            $this->assertSame('action_required', $state['group']);
        }
    }

    public function test_replaced_is_not_reported_as_renewal_offer_accepted(): void
    {
        $state = $this->resolver->resolve($this->subscription('replaced'), $this->now);

        $this->assertSame('replaced', $state['key']);
        $this->assertSame('Renewed', $state['label']);
        $this->assertSame('previous', $state['group']);
    }

    public function test_auto_renewing_subscription_within_30_days_is_renewing_soon(): void
    {
        $subscription = $this->subscription('active');
        $subscription->auto_renew = true;
        $subscription->next_billing_date = $this->now->modify('+30 days');
        $subscription->end_date = $this->now->modify('+30 days');

        $state = $this->resolver->resolve($subscription, $this->now);

        $this->assertSame('renewing_soon', $state['key']);
    }

    public function test_non_renewing_subscription_within_30_days_is_expiring_soon(): void
    {
        $subscription = $this->subscription('active');
        $subscription->auto_renew = false;
        $subscription->end_date = $this->now->modify('+30 days');

        $state = $this->resolver->resolve($subscription, $this->now);

        $this->assertSame('expiring_soon', $state['key']);
    }

    public function test_future_dates_outside_threshold_remain_active(): void
    {
        $subscription = $this->subscription('active');
        $subscription->auto_renew = false;
        $subscription->end_date = $this->now->modify('+31 days');

        $state = $this->resolver->resolve($subscription, $this->now);

        $this->assertSame('active', $state['key']);
    }

    public function test_past_end_date_is_expired_and_never_expiring_soon(): void
    {
        $subscription = $this->subscription('active');
        $subscription->auto_renew = false;
        $subscription->end_date = $this->now->modify('-1 day');

        $state = $this->resolver->resolve($subscription, $this->now);

        $this->assertSame('expired', $state['key']);
        $this->assertSame('previous', $state['group']);
    }

    public function test_cancelled_subscription_with_remaining_access_is_current(): void
    {
        $subscription = $this->subscription('cancelled');
        $subscription->end_date = $this->now->modify('+7 days');

        $state = $this->resolver->resolve($subscription, $this->now);

        $this->assertSame('cancelled', $state['key']);
        $this->assertSame('current', $state['group']);
    }

    private function subscription(string $status): Subscription
    {
        $subscription = new Subscription();
        $subscription->status = $status;
        $subscription->auto_renew = false;
        $subscription->cancel_at_period_end = false;
        $subscription->start_date = $this->now->modify('-1 year');
        $subscription->end_date = $this->now->modify('+1 year');

        return $subscription;
    }
}
