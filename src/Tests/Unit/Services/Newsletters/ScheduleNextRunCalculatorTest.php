<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Enums\Newsletters\ScheduleFrequency;
use App\Services\Newsletter\ScheduleNextRunCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ScheduleNextRunCalculatorTest extends TestCase
{
    private ScheduleNextRunCalculator $calculator;

    public function test_daily_schedule_returns_same_day_when_time_is_in_future(): void
    {
        $from = new \DateTimeImmutable('2026-02-23 10:00:00');

        $result = $this->calculator->calculate(
            ScheduleFrequency::DAILY,
            null,
            null,
            '12:00',
            $from
        );

        $this->assertEquals('2026-02-23 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    // -------------------------------------------------------------------------
    // Daily
    // -------------------------------------------------------------------------

    public function test_daily_schedule_advances_to_next_day_when_time_has_passed(): void
    {
        $from = new \DateTimeImmutable('2026-02-23 14:00:00');

        $result = $this->calculator->calculate(
            ScheduleFrequency::DAILY,
            null,
            null,
            '12:00',
            $from
        );

        $this->assertEquals('2026-02-24 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_daily_schedule_advances_to_next_day_when_time_is_exactly_now(): void
    {
        $from = new \DateTimeImmutable('2026-02-23 12:00:00');

        $result = $this->calculator->calculate(
            ScheduleFrequency::DAILY,
            null,
            null,
            '12:00',
            $from
        );

        $this->assertEquals('2026-02-24 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_weekly_schedule_returns_correct_upcoming_day(): void
    {
        // 2026-02-23 is a Monday (dow=1); target is Wednesday (dow=3)
        $from = new \DateTimeImmutable('2026-02-23 10:00:00');

        $result = $this->calculator->calculate(
            ScheduleFrequency::WEEKLY,
            3, // Wednesday
            null,
            '14:30',
            $from
        );

        $this->assertEquals('2026-02-25 14:30:00', $result->format('Y-m-d H:i:s'));
        $this->assertEquals('Wednesday', $result->format('l'));
    }

    // -------------------------------------------------------------------------
    // Weekly
    // -------------------------------------------------------------------------

    public function test_weekly_schedule_when_target_day_is_today_and_time_is_future(): void
    {
        // Monday 10:00, target Monday 14:00 → same day
        $from = new \DateTimeImmutable('2026-02-23 10:00:00');

        $result = $this->calculator->calculate(
            ScheduleFrequency::WEEKLY,
            1, // Monday
            null,
            '14:00',
            $from
        );

        $this->assertEquals('2026-02-23 14:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_weekly_schedule_when_target_day_is_today_and_time_has_passed(): void
    {
        // Monday 16:00, target Monday 14:00 → next Monday
        $from = new \DateTimeImmutable('2026-02-23 16:00:00');

        $result = $this->calculator->calculate(
            ScheduleFrequency::WEEKLY,
            1, // Monday
            null,
            '14:00',
            $from
        );

        $this->assertEquals('2026-03-02 14:00:00', $result->format('Y-m-d H:i:s'));
        $this->assertEquals('Monday', $result->format('l'));
    }

    public function test_weekly_schedule_targets_sunday_correctly(): void
    {
        $from = new \DateTimeImmutable('2026-02-23 10:00:00'); // Monday

        $result = $this->calculator->calculate(
            ScheduleFrequency::WEEKLY,
            0, // Sunday
            null,
            '09:00',
            $from
        );

        $this->assertEquals('Sunday', $result->format('l'));
        $this->assertEquals('2026-03-01 09:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_monthly_schedule_returns_correct_day_in_current_month(): void
    {
        $from = new \DateTimeImmutable('2026-02-10 10:00:00');

        $result = $this->calculator->calculate(
            ScheduleFrequency::MONTHLY,
            null,
            15,
            '09:00',
            $from
        );

        $this->assertEquals('2026-02-15 09:00:00', $result->format('Y-m-d H:i:s'));
    }

    // -------------------------------------------------------------------------
    // Monthly
    // -------------------------------------------------------------------------

    public function test_monthly_schedule_advances_to_next_month_when_day_has_passed(): void
    {
        $from = new \DateTimeImmutable('2026-02-20 10:00:00');

        $result = $this->calculator->calculate(
            ScheduleFrequency::MONTHLY,
            null,
            15,
            '09:00',
            $from
        );

        $this->assertEquals('2026-03-15 09:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_throws_when_weekly_missing_day_of_week(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('day_of_week');

        $this->calculator->calculate(ScheduleFrequency::WEEKLY, null, null, '12:00');
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_throws_when_weekly_day_of_week_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate(ScheduleFrequency::WEEKLY, 7, null, '12:00');
    }

    public function test_throws_when_monthly_missing_day_of_month(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('day_of_month');

        $this->calculator->calculate(ScheduleFrequency::MONTHLY, null, null, '12:00');
    }

    public function test_throws_when_monthly_day_of_month_exceeds_28(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate(ScheduleFrequency::MONTHLY, null, 29, '12:00');
    }

    public function test_throws_on_invalid_time_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HH:MM');

        $this->calculator->calculate(ScheduleFrequency::DAILY, null, null, '9:00');
    }

    public function test_throws_on_invalid_hour(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate(ScheduleFrequency::DAILY, null, null, '25:00');
    }

    protected function setUp(): void
    {
        $this->calculator = new ScheduleNextRunCalculator();
    }
}