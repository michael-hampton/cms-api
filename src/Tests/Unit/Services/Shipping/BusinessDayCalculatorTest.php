<?php

namespace App\Tests\Unit\Services\Shipping;

use App\Services\Shipping\BusinessDayCalculator;
use App\Services\Shipping\HolidayProviderInterface;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use DateTimeImmutable;
use Mockery;

class BusinessDayCalculatorTest extends FunctionalTestCase
{
    private $holidayProvider;
    private BusinessDayCalculator $calculator;

    public function testAddBusinessDaysSkipsWeekends(): void
    {
        // Friday
        $start = new DateTimeImmutable('2026-02-13');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturn(false);

        // Add 1 business day from Friday = Monday
        $result = $this->calculator->addBusinessDays($start, 1);

        $this->assertEquals('2026-02-16', $result->format('Y-m-d'));
    }

    public function testAddBusinessDaysSkipsHolidays(): void
    {
        // Monday, with Tuesday as holiday
        $start = new DateTimeImmutable('2026-02-16');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturnUsing(function (DateTimeImmutable $date) {
                return $date->format('Y-m-d') === '2026-02-17'; // Tuesday is holiday
            });

        // Add 1 business day from Monday = Wednesday (skipping Tuesday holiday)
        $result = $this->calculator->addBusinessDays($start, 1);

        $this->assertEquals('2026-02-18', $result->format('Y-m-d'));
    }

    public function testAddZeroBusinessDays(): void
    {
        $start = new DateTimeImmutable('2026-02-13');

        $result = $this->calculator->addBusinessDays($start, 0);

        $this->assertEquals($start->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testAddBusinessDaysFromWeekend(): void
    {
        // Saturday
        $start = new DateTimeImmutable('2026-02-14');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturn(false);

        // Add 1 business day from Saturday = Monday
        $result = $this->calculator->addBusinessDays($start, 1);

        $this->assertEquals('2026-02-16', $result->format('Y-m-d'));
    }

    public function testAddMultipleBusinessDaysSpanningHoliday(): void
    {
        // Monday
        $start = new DateTimeImmutable('2026-02-16');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturnUsing(function (DateTimeImmutable $date) {
                if ($date->format('Y-m-d') === '2026-02-17') {
                    return true;
                }

                return false;
            });

        // Add 3 business days = Friday (skip Wed holiday)
        $result = $this->calculator->addBusinessDays($start, 3);

        $this->assertEquals('2026-02-20', $result->format('Y-m-d'));
    }

    public function testWorksAcrossYearBoundary(): void
    {
        // Dec 30 2025 (Tuesday)
        $start = new DateTimeImmutable('2025-12-30');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturnUsing(function (DateTimeImmutable $date) {
                // Jan 1 2026 is holiday
                return $date->format('Y-m-d') === '2026-01-01';
            });

        // Add 3 business days
        $result = $this->calculator->addBusinessDays($start, 3);

        $this->assertEquals('2026-01-05', $result->format('Y-m-d'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->holidayProvider = Mockery::mock(HolidayProviderInterface::class);
        $this->calculator = new BusinessDayCalculator($this->holidayProvider);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}