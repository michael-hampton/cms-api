<?php

namespace App\Tests\Unit\Services\Subscriptions\ValueObjects;

use App\Services\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testFromCentsCreatesCorrectAmount(): void
    {
        $money = Money::fromCents(1999, 'USD');

        $this->assertEquals(1999, $money->toCents());
        $this->assertEquals(19.99, $money->toDecimal());
        $this->assertEquals('USD', $money->getCurrency());
    }

    public function testFromDecimalHandlesRounding(): void
    {
        // Test normal rounding
        $money1 = Money::fromDecimal(19.99, 'USD');
        $this->assertEquals(1999, $money1->toCents());

        // Test rounding up
        $money2 = Money::fromDecimal(19.995, 'USD');
        $this->assertEquals(2000, $money2->toCents());

        // Test rounding down
        $money3 = Money::fromDecimal(19.994, 'USD');
        $this->assertEquals(1999, $money3->toCents());

        // Test floating point precision issues
        $money4 = Money::fromDecimal(0.1 + 0.2, 'USD'); // Classic FP issue
        $this->assertEquals(30, $money4->toCents()); // 0.3 = 30 cents
    }

    public function testSubtractionWorks(): void
    {
        $money1 = Money::fromDecimal(50.00, 'USD');
        $money2 = Money::fromDecimal(30.00, 'USD');

        $result = $money1->subtract($money2);

        $this->assertEquals(20.00, $result->toDecimal());
        $this->assertEquals(2000, $result->toCents());
    }

    public function testSubtractionCanResultInNegative(): void
    {
        $money1 = Money::fromDecimal(30.00, 'USD');
        $money2 = Money::fromDecimal(50.00, 'USD');

        $result = $money1->subtract($money2);

        $this->assertEquals(-20.00, $result->toDecimal());
        $this->assertTrue($result->isNegative());
    }

    public function testAdditionWorks(): void
    {
        $money1 = Money::fromDecimal(25.50, 'USD');
        $money2 = Money::fromDecimal(10.25, 'USD');

        $result = $money1->add($money2);

        $this->assertEquals(35.75, $result->toDecimal());
        $this->assertEquals(3575, $result->toCents());
    }

    public function testMultiplicationPreservesAccuracy(): void
    {
        $money = Money::fromDecimal(10.00, 'USD');

        // Multiply by decimal
        $result1 = $money->multiply(1.5);
        $this->assertEquals(15.00, $result1->toDecimal());

        // Multiply by fraction (33.33% of 30.00 = 10.00)
        $money2 = Money::fromDecimal(30.00, 'USD');
        $result2 = $money2->multiply(1.0 / 3.0);
        $this->assertEquals(1000, $result2->toCents()); // Should round correctly
    }

    public function testDivisionWorks(): void
    {
        $money = Money::fromDecimal(100.00, 'USD');

        $result = $money->divide(4);

        $this->assertEquals(25.00, $result->toDecimal());
        $this->assertEquals(2500, $result->toCents());
    }

    public function testDivisionByZeroThrowsException(): void
    {
        $money = Money::fromDecimal(100.00, 'USD');

        $this->expectException(InvalidArgumentException::class);
        $money->divide(0);
    }

    public function testThrowsExceptionForDifferentCurrencies(): void
    {
        $usd = Money::fromDecimal(100.00, 'USD');
        $eur = Money::fromDecimal(100.00, 'EUR');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot subtract EUR from USD');

        $usd->subtract($eur);
    }

    public function testAdditionThrowsExceptionForDifferentCurrencies(): void
    {
        $usd = Money::fromDecimal(100.00, 'USD');
        $eur = Money::fromDecimal(100.00, 'EUR');

        $this->expectException(InvalidArgumentException::class);
        $usd->add($eur);
    }

    public function testIsPositiveWorks(): void
    {
        $positive = Money::fromDecimal(10.00, 'USD');
        $zero = Money::fromCents(0, 'USD');
        $negative = Money::fromDecimal(-10.00, 'USD');

        $this->assertTrue($positive->isPositive());
        $this->assertFalse($zero->isPositive());
        $this->assertFalse($negative->isPositive());
    }

    public function testIsNegativeWorks(): void
    {
        $positive = Money::fromDecimal(10.00, 'USD');
        $zero = Money::fromCents(0, 'USD');
        $negative = Money::fromDecimal(-10.00, 'USD');

        $this->assertFalse($positive->isNegative());
        $this->assertFalse($zero->isNegative());
        $this->assertTrue($negative->isNegative());
    }

    public function testIsZeroWorks(): void
    {
        $positive = Money::fromDecimal(10.00, 'USD');
        $zero = Money::fromCents(0, 'USD');
        $negative = Money::fromDecimal(-10.00, 'USD');

        $this->assertFalse($positive->isZero());
        $this->assertTrue($zero->isZero());
        $this->assertFalse($negative->isZero());
    }

    public function testComparisonWorks(): void
    {
        $money1 = Money::fromDecimal(50.00, 'USD');
        $money2 = Money::fromDecimal(30.00, 'USD');
        $money3 = Money::fromDecimal(50.00, 'USD');

        $this->assertEquals(1, $money1->compareTo($money2)); // 50 > 30
        $this->assertEquals(-1, $money2->compareTo($money1)); // 30 < 50
        $this->assertEquals(0, $money1->compareTo($money3)); // 50 == 50
    }

    public function testComparisonThrowsExceptionForDifferentCurrencies(): void
    {
        $usd = Money::fromDecimal(100.00, 'USD');
        $eur = Money::fromDecimal(100.00, 'EUR');

        $this->expectException(InvalidArgumentException::class);
        $usd->compareTo($eur);
    }

    public function testEqualsWorks(): void
    {
        $money1 = Money::fromDecimal(50.00, 'USD');
        $money2 = Money::fromDecimal(50.00, 'USD');
        $money3 = Money::fromDecimal(30.00, 'USD');

        $this->assertTrue($money1->equals($money2));
        $this->assertFalse($money1->equals($money3));
    }

    public function testGreaterThanWorks(): void
    {
        $money1 = Money::fromDecimal(50.00, 'USD');
        $money2 = Money::fromDecimal(30.00, 'USD');

        $this->assertTrue($money1->greaterThan($money2));
        $this->assertFalse($money2->greaterThan($money1));
    }

    public function testLessThanWorks(): void
    {
        $money1 = Money::fromDecimal(30.00, 'USD');
        $money2 = Money::fromDecimal(50.00, 'USD');

        $this->assertTrue($money1->lessThan($money2));
        $this->assertFalse($money2->lessThan($money1));
    }

    public function testFormatDisplaysCorrectly(): void
    {
        $usd = Money::fromDecimal(1234.56, 'USD');
        $this->assertEquals('$1,234.56', $usd->format());

        $eur = Money::fromDecimal(1234.56, 'EUR');
        $this->assertEquals('€1,234.56', $eur->format());

        $gbp = Money::fromDecimal(1234.56, 'GBP');
        $this->assertEquals('£1,234.56', $gbp->format());

        // Unknown currency shows code
        $xxx = Money::fromDecimal(1234.56, 'XXX');
        $this->assertStringContainsString('XXX', $xxx->format());
    }

    public function testToArrayReturnsAllInformation(): void
    {
        $money = Money::fromDecimal(19.99, 'USD');
        $array = $money->toArray();

        $this->assertEquals(19.99, $array['amount']);
        $this->assertEquals(1999, $array['cents']);
        $this->assertEquals('USD', $array['currency']);
    }

    public function testToStringUsesFormat(): void
    {
        $money = Money::fromDecimal(99.99, 'USD');
        $this->assertEquals('$99.99', (string)$money);
    }

    public function testCurrencyIsNormalized(): void
    {
        $money = Money::fromDecimal(10.00, 'usd');
        $this->assertEquals('USD', $money->getCurrency());
    }

    public function testAccuracyWithComplexCalculations(): void
    {
        // Simulating proration calculation: $30 difference over 30 days, 10 days remaining
        $priceDiff = Money::fromDecimal(30.00, 'USD');
        $prorated = $priceDiff->multiply(10.0 / 30.0);

        $this->assertEquals(10.00, $prorated->toDecimal());
        $this->assertEquals(1000, $prorated->toCents());
    }

    public function testNoFloatingPointPrecisionLoss(): void
    {
        // Classic floating point issue: 0.1 + 0.1 + 0.1 != 0.3
        $money = Money::fromCents(0, 'USD');

        for ($i = 0; $i < 3; $i++) {
            $money = $money->add(Money::fromDecimal(0.1, 'USD'));
        }

        // Should be exactly 30 cents, no precision loss
        $this->assertEquals(30, $money->toCents());
        $this->assertEquals(0.30, $money->toDecimal());
    }

    public function testLargeAmountsHandledCorrectly(): void
    {
        // Test with large subscription amounts
        $large = Money::fromDecimal(999999.99, 'USD');
        $this->assertEquals(99999999, $large->toCents());

        $result = $large->subtract(Money::fromDecimal(999999.00, 'USD'));
        $this->assertEquals(0.99, $result->toDecimal());
    }
}