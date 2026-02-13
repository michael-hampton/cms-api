<?php

namespace App\Tests\Unit\Services\Shipping;

use App\Services\Shipping\BusinessDayCalculator;
use App\Services\Shipping\CutOffTimeResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use DateTimeImmutable;
use Mockery;

class CutOffTimeResolverTest extends FunctionalTestCase
{
    private $calculator;
    private CutOffTimeResolver $resolver;

    public function testBeforeCutOffReturnsSameDay(): void
    {
        $orderDate = new DateTimeImmutable('2026-02-13 13:00:00');
        $cutOffTime = '14:00';

        $result = $this->resolver->resolveStartDate($orderDate, $cutOffTime);

        $this->assertEquals('2026-02-13', $result->format('Y-m-d'));
    }

    public function testAfterCutOffReturnsNextBusinessDay(): void
    {
        $orderDate = new DateTimeImmutable('2026-02-13 15:00:00');
        $nextBusinessDay = new DateTimeImmutable('2026-02-16'); // Monday
        $cutOffTime = '14:00';

        $this->calculator->shouldReceive('addBusinessDays')
            ->once()
            ->with(Mockery::on(function ($date) use ($orderDate) {
                return $date->format('Y-m-d H:i:s') === $orderDate->format('Y-m-d H:i:s');
            }), 1)
            ->andReturn($nextBusinessDay);

        $result = $this->resolver->resolveStartDate($orderDate, $cutOffTime);

        $this->assertEquals('2026-02-16', $result->format('Y-m-d'));
    }

    public function testFridayAfterCutOffReturnsMonday(): void
    {
        // Friday 15:01
        $orderDate = new DateTimeImmutable('2026-02-13 15:01:00');
        $monday = new DateTimeImmutable('2026-02-16');
        $cutOffTime = '14:00';

        $this->calculator->shouldReceive('addBusinessDays')
            ->once()
            ->andReturn($monday);

        $result = $this->resolver->resolveStartDate($orderDate, $cutOffTime);

        $this->assertEquals('2026-02-16', $result->format('Y-m-d'));
    }

    public function testFridayBeforeCutOffReturnsFriday(): void
    {
        // Friday 13:00
        $orderDate = new DateTimeImmutable('2026-02-13 13:00:00');
        $cutOffTime = '14:00';

        $result = $this->resolver->resolveStartDate($orderDate, $cutOffTime);

        $this->assertEquals('2026-02-13', $result->format('Y-m-d'));
    }

    public function testExactlyAtCutOffReturnsSameDay(): void
    {
        $orderDate = new DateTimeImmutable('2026-02-13 14:00:00');
        $cutOffTime = '14:00';

        $result = $this->resolver->resolveStartDate($orderDate, $cutOffTime);

        $this->assertEquals('2026-02-13', $result->format('Y-m-d'));
    }

    public function testOrderOnBankHolidayAfterCutOff(): void
    {
        // Order on Monday bank holiday at 15:00
        $orderDate = new DateTimeImmutable('2026-01-01 15:00:00'); // New Year
        $nextBusinessDay = new DateTimeImmutable('2026-01-02'); // Next business day
        $cutOffTime = '14:00';

        $this->calculator->shouldReceive('addBusinessDays')
            ->once()
            ->with($orderDate, 1)
            ->andReturn($nextBusinessDay);

        $result = $this->resolver->resolveStartDate($orderDate, $cutOffTime);

        $this->assertEquals('2026-01-02', $result->format('Y-m-d'));
    }

    public function testOrderOnBankHolidayBeforeCutOff(): void
    {
        // Order on bank holiday but before cut-off
        $orderDate = new DateTimeImmutable('2026-01-01 10:00:00');
        $cutOffTime = '14:00';

        $result = $this->resolver->resolveStartDate($orderDate, $cutOffTime);

        // Returns same day (even though it's a holiday)
        $this->assertEquals('2026-01-01', $result->format('Y-m-d'));
    }

    public function testMorningOrderWithEarlyCutOff(): void
    {
        $orderDate = new DateTimeImmutable('2026-02-13 09:00:00');
        $cutOffTime = '12:00';

        $result = $this->resolver->resolveStartDate($orderDate, $cutOffTime);

        $this->assertEquals('2026-02-13', $result->format('Y-m-d'));
    }

    public function testLateNightOrderWithEarlyCutOff(): void
    {
        $orderDate = new DateTimeImmutable('2026-02-13 23:00:00');
        $nextBusinessDay = new DateTimeImmutable('2026-02-16'); // Monday
        $cutOffTime = '12:00';

        $this->calculator->shouldReceive('addBusinessDays')
            ->once()
            ->with($orderDate, 1)
            ->andReturn($nextBusinessDay);

        $result = $this->resolver->resolveStartDate($orderDate, $cutOffTime);

        $this->assertEquals('2026-02-16', $result->format('Y-m-d'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = Mockery::mock(BusinessDayCalculator::class);
        $this->resolver = new CutOffTimeResolver($this->calculator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}