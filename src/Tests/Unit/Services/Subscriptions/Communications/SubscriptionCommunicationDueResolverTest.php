<?php

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Enums\Subscriptions\CommunicationRelativeTo;
use App\Models\Subscription;
use App\Models\SubscriptionCommunicationSchedule;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationDueResolver;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionCommunicationDueResolverTest extends TestCase
{
    private SubscriptionCommunicationDueResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new SubscriptionCommunicationDueResolver();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_relative_renewal_schedule_is_due_on_correct_date(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->renewal_date = '2026-07-01';

        $schedule = $this->makeRelativeSchedule(
            relativeTo: CommunicationRelativeTo::RENEWAL_DATE,
            offsetDays: -30,
        );

        $result = $this->resolver->isDue(
            $subscription,
            $schedule,
            new DateTimeImmutable('2026-06-01')
        );

        $this->assertTrue($result);
    }

    public function test_relative_schedule_is_not_due_before_target_date(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->renewal_date = '2026-07-01';

        $schedule = $this->makeRelativeSchedule(
            relativeTo: CommunicationRelativeTo::RENEWAL_DATE,
            offsetDays: -30,
        );

        $result = $this->resolver->isDue(
            $subscription,
            $schedule,
            new DateTimeImmutable('2026-05-31')
        );

        $this->assertFalse($result);
    }

    public function test_relative_schedule_is_not_due_after_target_date(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->renewal_date = '2026-07-01';

        $schedule = $this->makeRelativeSchedule(
            relativeTo: CommunicationRelativeTo::RENEWAL_DATE,
            offsetDays: -30,
        );

        $result = $this->resolver->isDue(
            $subscription,
            $schedule,
            new DateTimeImmutable('2026-06-02')
        );

        $this->assertFalse($result);
    }

    public function test_fixed_date_schedule_is_due_on_matching_date(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $schedule = Mockery::mock(SubscriptionCommunicationSchedule::class)->makePartial();
        $schedule->trigger_type = 'fixed';
        $schedule->fixed_date = new DateTimeImmutable('2026-08-15');
        $schedule->is_active = true;

        $result = $this->resolver->isDue(
            $subscription,
            $schedule,
            new DateTimeImmutable('2026-08-15')
        );

        $this->assertTrue($result);
    }

    public function test_fixed_date_schedule_is_not_due_on_different_date(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $schedule = Mockery::mock(SubscriptionCommunicationSchedule::class)->makePartial();
        $schedule->trigger_type = 'fixed';
        $schedule->fixed_date = new DateTimeImmutable('2026-08-15');
        $schedule->is_active = true;

        $result = $this->resolver->isDue(
            $subscription,
            $schedule,
            new DateTimeImmutable('2026-08-16')
        );

        $this->assertFalse($result);
    }

    public function test_missing_renewal_date_returns_false(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->renewal_date = null;

        $schedule = $this->makeRelativeSchedule(
            relativeTo: CommunicationRelativeTo::RENEWAL_DATE,
            offsetDays: -30,
        );

        $result = $this->resolver->isDue(
            $subscription,
            $schedule,
            new DateTimeImmutable('today')
        );

        $this->assertFalse($result);
    }

    public function test_missing_ccc_expiry_date_returns_false(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->ccc_expiry_date = null;

        $schedule = $this->makeRelativeSchedule(
            relativeTo: CommunicationRelativeTo::CCC_EXPIRY_DATE,
            offsetDays: -7,
        );

        $result = $this->resolver->isDue(
            $subscription,
            $schedule,
            new DateTimeImmutable('today')
        );

        $this->assertFalse($result);
    }

    public function test_inactive_schedule_is_never_due(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $subscription->renewal_date = (new DateTimeImmutable('today'))
            ->modify('+30 days')
            ->format('Y-m-d');

        $schedule = $this->makeRelativeSchedule(
            relativeTo: CommunicationRelativeTo::RENEWAL_DATE,
            offsetDays: -30,
            isActive: false,
        );

        $result = $this->resolver->isDue(
            $subscription,
            $schedule,
            new DateTimeImmutable('today')
        );

        $this->assertFalse($result);
    }

    public function test_ccc_expiry_relative_schedule_resolves_correctly(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->ccc_expiry_date = '2026-09-01';

        $schedule = $this->makeRelativeSchedule(
            relativeTo: CommunicationRelativeTo::CCC_EXPIRY_DATE,
            offsetDays: -7,
        );

        $result = $this->resolver->isDue(
            $subscription,
            $schedule,
            new DateTimeImmutable('2026-08-25')
        );

        $this->assertTrue($result);
    }

    private function makeRelativeSchedule(
        CommunicationRelativeTo $relativeTo,
        int $offsetDays,
        bool $isActive = true,
    ): SubscriptionCommunicationSchedule {
        $schedule = Mockery::mock(SubscriptionCommunicationSchedule::class)->makePartial();
        $schedule->trigger_type = 'relative';
        $schedule->relative_to = $relativeTo;
        $schedule->offset_days = $offsetDays;
        $schedule->is_active = $isActive;

        return $schedule;
    }
}