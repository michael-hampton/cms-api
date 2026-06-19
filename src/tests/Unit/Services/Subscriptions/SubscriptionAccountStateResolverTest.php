<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Services\Subscriptions\SubscriptionAccountStateResolver;
use App\Tests\Support\MocksSubscriptionModels;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SubscriptionAccountStateResolverTest extends TestCase
{
    use MocksSubscriptionModels;

    private SubscriptionAccountStateResolver $resolver;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SubscriptionAccountStateResolver();
        $this->now = new DateTimeImmutable('2026-06-19 12:00:00');
    }

    public function test_paused_subscription_is_current_and_shows_resume_context(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'paused',
            'pause_until' => $this->now->modify('+30 days'),
        ]), $this->now);

        $this->assertSame('paused', $state['key']);
        $this->assertSame('current', $state['group']);
        $this->assertSame('Paused', $state['label']);
        $this->assertSame('Paused until', $state['date_label']);
        $this->assertSame('19 Jul 2026', $state['date_value']);
    }

    public function test_indefinitely_paused_subscription_has_clear_copy(): void
    {
        $state = $this->resolver->resolve($this->subscription(['status' => 'paused']), $this->now);

        $this->assertSame('paused', $state['key']);
        $this->assertSame('This subscription is paused until you resume it.', $state['copy']);
        $this->assertNull($state['date_label']);
    }

    public function test_suspended_and_past_due_states_take_precedence(): void
    {
        foreach (['suspended', 'past_due', 'unpaid', 'failed'] as $status) {
            $state = $this->resolver->resolve($this->subscription(['status' => $status]), $this->now);
            $this->assertSame('suspended', $state['key']);
            $this->assertSame('action_required', $state['group']);
        }
    }

    public function test_processing_states_are_action_required(): void
    {
        foreach (['incomplete', 'retrying', 'pending'] as $status) {
            $state = $this->resolver->resolve($this->subscription(['status' => $status]), $this->now);
            $this->assertSame('processing', $state['key']);
            $this->assertSame('action_required', $state['group']);
        }
    }

    public function test_replaced_is_not_reported_as_renewal_offer_accepted(): void
    {
        $state = $this->resolver->resolve($this->subscription(['status' => 'replaced']), $this->now);
        $this->assertSame('replaced', $state['key']);
        $this->assertSame('Renewed', $state['label']);
        $this->assertSame('previous', $state['group']);
    }

    public function test_auto_renewing_subscription_within_30_days_is_renewing_soon(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'auto_renew' => true,
            'next_billing_date' => $this->now->modify('+30 days'),
            'end_date' => $this->now->modify('+30 days'),
        ]), $this->now);

        $this->assertSame('renewing_soon', $state['key']);
    }

    public function test_non_renewing_subscription_within_30_days_is_expiring_soon(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'end_date' => $this->now->modify('+30 days'),
        ]), $this->now);

        $this->assertSame('expiring_soon', $state['key']);
    }

    public function test_future_dates_outside_threshold_remain_active(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'end_date' => $this->now->modify('+31 days'),
        ]), $this->now);

        $this->assertSame('active', $state['key']);
    }

    public function test_past_end_date_is_expired_and_never_expiring_soon(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'end_date' => $this->now->modify('-1 day'),
        ]), $this->now);

        $this->assertSame('expired', $state['key']);
        $this->assertSame('previous', $state['group']);
    }

    public function test_cancelled_subscription_with_remaining_access_is_current(): void
    {
        $state = $this->resolver->resolve($this->subscription([
            'status' => 'cancelled',
            'end_date' => $this->now->modify('+7 days'),
        ]), $this->now);

        $this->assertSame('cancelled', $state['key']);
        $this->assertSame('current', $state['group']);
    }

    private function subscription(array $overrides = []): \App\Models\Subscription
    {
        return $this->mockSubscription(array_merge([
            'start_date' => $this->now->modify('-1 year'),
            'end_date' => $this->now->modify('+1 year'),
        ], $overrides), [
            'isCancellationScheduled' => false,
        ]);
    }
}
