<?php

namespace App\Tests\Unit\Services\Subscriptions\Calculators;

use App\Services\Subscriptions\Calculators\SubscriptionPricingCalculator;
use App\Services\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class SubscriptionPricingCalculatorTest extends TestCase
{
    private SubscriptionPricingCalculator $calculator;

    public function testCalculateFinalPriceWithDiscount(): void
    {
        $basePrice = Money::fromDecimal(99.99, 'USD');
        $discount = Money::fromDecimal(10.00, 'USD');

        $result = $this->calculator->calculateFinalPrice($basePrice, $discount);

        $this->assertEquals(89.99, $result->toDecimal());
    }

    public function testCalculateFinalPriceEnsuresNonNegative(): void
    {
        $basePrice = Money::fromDecimal(10.00, 'USD');
        $discount = Money::fromDecimal(20.00, 'USD');

        $result = $this->calculator->calculateFinalPrice($basePrice, $discount);

        $this->assertEquals(0, $result->toDecimal());
    }

    public function testValidateDiscountSuccess(): void
    {
        $basePrice = Money::fromDecimal(99.99, 'USD');
        $discount = Money::fromDecimal(10.00, 'USD');

        $this->calculator->validateDiscount($basePrice, $discount);

        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateDiscountThrowsForNegativeDiscount(): void
    {
        $basePrice = Money::fromDecimal(99.99, 'USD');
        $discount = Money::fromCents(-1000, 'USD');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount amount cannot be negative');

        $this->calculator->validateDiscount($basePrice, $discount);
    }

    public function testValidateDiscountThrowsWhenExceedsPrice(): void
    {
        $basePrice = Money::fromDecimal(50.00, 'USD');
        $discount = Money::fromDecimal(60.00, 'USD');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount amount cannot exceed base price');

        $this->calculator->validateDiscount($basePrice, $discount);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SubscriptionPricingCalculator();
    }
}