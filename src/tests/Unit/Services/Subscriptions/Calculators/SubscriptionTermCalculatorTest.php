<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Calculators;

use App\Models\Subscription;
use App\Services\Subscriptions\Calculators\SubscriptionTermCalculator;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionTermCalculatorTest extends TestCase
{
    private SubscriptionTermCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SubscriptionTermCalculator();
    }

    private function makeSubscription(): Subscription
    {
        return Mockery::mock(Subscription::class)->makePartial();
    }

    public function test_age_days_is_zero_when_start_date_is_missing(): void
    {
        $subscription = $this->makeSubscription();

        $this->assertSame(0, $this->calculator->ageDays($subscription));
    }

    public function test_age_days_counts_from_start_date_to_now(): void
    {
        $now = new DateTimeImmutable('2026-07-10');
        $subscription = $this->makeSubscription();
        $subscription->start_date = new DateTimeImmutable('2026-06-10');

        $this->assertSame(30, $this->calculator->ageDays($subscription, $now));
    }

    public function test_age_days_is_zero_for_a_start_date_in_the_future(): void
    {
        $now = new DateTimeImmutable('2026-07-10');
        $subscription = $this->makeSubscription();
        $subscription->start_date = new DateTimeImmutable('2026-08-10');

        $this->assertSame(0, $this->calculator->ageDays($subscription, $now));
    }

    public function test_remaining_term_days_is_null_when_end_date_is_missing(): void
    {
        $subscription = $this->makeSubscription();

        $this->assertNull($this->calculator->remainingTermDays($subscription));
    }

    public function test_remaining_term_days_counts_down_to_end_date(): void
    {
        $now = new DateTimeImmutable('2026-07-10');
        $subscription = $this->makeSubscription();
        $subscription->end_date = new DateTimeImmutable('2026-07-20');

        $this->assertSame(10, $this->calculator->remainingTermDays($subscription, $now));
    }

    public function test_remaining_term_days_is_zero_once_the_end_date_has_passed(): void
    {
        $now = new DateTimeImmutable('2026-07-10');
        $subscription = $this->makeSubscription();
        $subscription->end_date = new DateTimeImmutable('2026-06-01');

        $this->assertSame(0, $this->calculator->remainingTermDays($subscription, $now));
    }

    public function test_pauses_used_this_term_is_zero_when_never_resumed(): void
    {
        $subscription = $this->makeSubscription();

        $this->assertSame(0, $this->calculator->pausesUsedThisTerm($subscription));
    }

    public function test_pauses_used_this_term_is_one_when_resumed_within_the_current_period(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->current_period_start = new DateTimeImmutable('2026-06-01');
        $subscription->resumed_at = new DateTimeImmutable('2026-06-15');

        $this->assertSame(1, $this->calculator->pausesUsedThisTerm($subscription));
    }

    public function test_pauses_used_this_term_is_zero_when_the_resume_predates_the_current_period(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->current_period_start = new DateTimeImmutable('2026-06-01');
        $subscription->resumed_at = new DateTimeImmutable('2026-05-01');

        $this->assertSame(0, $this->calculator->pausesUsedThisTerm($subscription));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
