<?php

namespace App\Tests\Unit\Services\Shipping;

use App\Services\Shipping\BusinessDayCalculator;
use App\Services\Shipping\HolidayProviderInterface;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BusinessDayCalculatorYearBoundaryTest extends FunctionalTestCase
{
    private $holidayProvider;
    private BusinessDayCalculator $calculator;

    public function testOrderOn29December(): void
    {
        $start = new \DateTimeImmutable('2025-12-29');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturnUsing(function (\DateTimeImmutable $date) {
                return in_array($date->format('Y-m-d'), [
                    '2025-12-25',
                    '2025-12-26',
                    '2026-01-01'
                ]);
            });

        $result = $this->calculator->addBusinessDays($start, 3);

        $this->assertEquals('2026-01-02', $result->format('Y-m-d'));
    }

    public function testOrderOn30December(): void
    {
        $start = new \DateTimeImmutable('2025-12-30');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturnUsing(function (\DateTimeImmutable $date) {
                return $date->format('Y-m-d') === '2026-01-01';
            });

        $result = $this->calculator->addBusinessDays($start, 3);

        $this->assertEquals('2026-01-05', $result->format('Y-m-d'));
    }

    public function testOrderOn31December(): void
    {
        $start = new \DateTimeImmutable('2025-12-31');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturnUsing(function (\DateTimeImmutable $date) {
                return $date->format('Y-m-d') === '2026-01-01';
            });

        $result = $this->calculator->addBusinessDays($start, 2);

        $this->assertEquals('2026-01-05', $result->format('Y-m-d'));
    }

    public function testDispatchSpansChristmasAndWeekend(): void
    {
        $start = new \DateTimeImmutable('2025-12-22');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturnUsing(function (\DateTimeImmutable $date) {
                return in_array($date->format('Y-m-d'), [
                    '2025-12-25', // Christmas
                    '2025-12-26'  // Boxing Day
                ]);
            });

        $result = $this->calculator->addBusinessDays($start, 5);

        $this->assertEquals('2025-12-31', $result->format('Y-m-d'));
    }

    public function testTransitSpansYearBoundary(): void
    {
        $start = new \DateTimeImmutable('2025-12-26');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturnUsing(function (\DateTimeImmutable $date) {
                return in_array($date->format('Y-m-d'), [
                    '2025-12-26',
                    '2026-01-01'
                ]);
            });

        $result = $this->calculator->addBusinessDays($start, 5);

        $this->assertEquals('2026-01-05', $result->format('Y-m-d'));
    }

    public function testNewYearWithWeekendOverlap(): void
    {
        $start = new \DateTimeImmutable('2025-12-27');

        $this->holidayProvider->shouldReceive('isHoliday')
            ->andReturnUsing(function (\DateTimeImmutable $date) {
                return $date->format('Y-m-d') === '2026-01-01';
            });

        $result = $this->calculator->addBusinessDays($start, 3);

        $this->assertEquals('2025-12-31', $result->format('Y-m-d'));
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