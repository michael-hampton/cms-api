<?php

namespace App\Tests\Unit\Services\Billing\Preorder\Calculators;

use App\Enums\Subscriptions\BillingPeriod;
use App\Services\Subscriptions\Calculators\SubscriptionDateCalculator;
use PHPUnit\Framework\TestCase;

class SubscriptionDateCalculatorTest extends TestCase
{
    private SubscriptionDateCalculator $calculator;

    public function testCalculateEndDateMonthly(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01 00:00:00');
        $endDate = $this->calculator->calculateEndDate($startDate, BillingPeriod::MONTHLY);

        $this->assertEquals('2024-02-01', $endDate->format('Y-m-d'));
    }

    public function testCalculateEndDateYearly(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01 00:00:00');
        $endDate = $this->calculator->calculateEndDate($startDate, BillingPeriod::YEARLY);

        $this->assertEquals('2025-01-01', $endDate->format('Y-m-d'));
    }

    public function testCalculateEndDateTwoYear(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01 00:00:00');
        $endDate = $this->calculator->calculateEndDate($startDate, BillingPeriod::ONE_TIME);

        $this->assertEquals('2026-01-01', $endDate->format('Y-m-d'));
    }

    public function testNormalizeStartDateWithNull(): void
    {
        $result = $this->calculator->normalizeStartDate(null);

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    public function testNormalizeStartDateWithFullDateTime(): void
    {
        $result = $this->calculator->normalizeStartDate('2024-06-15 14:30:45');

        $this->assertEquals('2024-06-15', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    public function testNormalizeStartDateWithDateOnly(): void
    {
        $result = $this->calculator->normalizeStartDate('2024-06-15');

        $this->assertEquals('2024-06-15', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    public function testNormalizeStartDateThrowsForInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format: not-a-date');

        $this->calculator->normalizeStartDate('not-a-date');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SubscriptionDateCalculator();
    }
}