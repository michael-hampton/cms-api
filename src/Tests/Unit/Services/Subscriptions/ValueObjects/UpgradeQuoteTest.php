<?php

namespace App\Tests\Unit\Services\Subscriptions\ValueObjects;

use App\Services\Subscriptions\ValueObjects\Money;
use App\Services\Subscriptions\ValueObjects\UpgradeQuote;
use PHPUnit\Framework\TestCase;

class UpgradeQuoteTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $amount = Money::fromDecimal(20.00, 'USD');
        $quote = new UpgradeQuote($amount, true, 15, true);

        $this->assertEquals($amount, $quote->getAmount());
        $this->assertTrue($quote->isProrated());
        $this->assertEquals(15, $quote->getRemainingDays());
        $this->assertTrue($quote->isEstimate());
    }

    public function testNonProratedQuote(): void
    {
        $amount = Money::fromDecimal(20.00, 'USD');
        $quote = new UpgradeQuote($amount, false, null, false);

        $this->assertFalse($quote->isProrated());
        $this->assertNull($quote->getRemainingDays());
        $this->assertFalse($quote->isEstimate());
    }

    public function testGetEstimateDisclaimerReturnsTextForEstimates(): void
    {
        $amount = Money::fromDecimal(20.00, 'USD');
        $estimate = new UpgradeQuote($amount, true, 15, true);

        $disclaimer = $estimate->getEstimateDisclaimer();

        $this->assertNotNull($disclaimer);
        $this->assertStringContainsString('proration', $disclaimer);
    }

    public function testGetEstimateDisclaimerReturnsNullForNonEstimates(): void
    {
        $amount = Money::fromDecimal(20.00, 'USD');
        $definite = new UpgradeQuote($amount, false, null, false);

        $this->assertNull($definite->getEstimateDisclaimer());
    }

    public function testToArrayContainsAllInformation(): void
    {
        $amount = Money::fromDecimal(15.50, 'USD');
        $quote = new UpgradeQuote($amount, true, 10, true);

        $array = $quote->toArray();

        $this->assertEquals(15.50, $array['amount']);
        $this->assertEquals(1550, $array['amount_in_cents']);
        $this->assertEquals('USD', $array['currency']);
        $this->assertTrue($array['is_prorated']);
        $this->assertEquals(10, $array['remaining_days']);
        $this->assertTrue($array['is_estimate']);
        $this->assertNotNull($array['estimate_disclaimer']);
    }

    public function testGetSummaryMessageForProratedEstimate(): void
    {
        $amount = Money::fromDecimal(10.00, 'USD');
        $quote = new UpgradeQuote($amount, true, 15, true);

        $summary = $quote->getSummaryMessage();

        $this->assertStringContainsString('$10.00', $summary);
        $this->assertStringContainsString('prorated', $summary);
        $this->assertStringContainsString('15', $summary);
        $this->assertStringContainsString('estimated', $summary);
    }

    public function testGetSummaryMessageForNonProratedDefinite(): void
    {
        $amount = Money::fromDecimal(20.00, 'USD');
        $quote = new UpgradeQuote($amount, false, null, false);

        $summary = $quote->getSummaryMessage();

        $this->assertStringContainsString('$20.00', $summary);
        $this->assertStringNotContainsString('prorated', $summary);
        $this->assertStringNotContainsString('estimated', $summary);
    }

    public function testQuoteWithZeroAmount(): void
    {
        $amount = Money::fromCents(0, 'USD');
        $quote = new UpgradeQuote($amount, false, null, false);

        $this->assertEquals(0.00, $quote->getAmount()->toDecimal());
        $this->assertStringContainsString('$0.00', $quote->getSummaryMessage());
    }

    public function testQuoteEstimateDefaultsToTrue(): void
    {
        $amount = Money::fromDecimal(10.00, 'USD');
        $quote = new UpgradeQuote($amount, true, 10);

        $this->assertTrue($quote->isEstimate());
    }

    public function testQuoteForDifferentCurrencies(): void
    {
        $eur = Money::fromDecimal(25.00, 'EUR');
        $quote = new UpgradeQuote($eur, false, null, false);

        $array = $quote->toArray();
        $this->assertEquals('EUR', $array['currency']);
    }

    public function testProratedQuoteWithoutRemainingDays(): void
    {
        // Edge case: prorated but no remaining days tracked
        $amount = Money::fromDecimal(15.00, 'USD');
        $quote = new UpgradeQuote($amount, true, null, true);

        $this->assertTrue($quote->isProrated());
        $this->assertNull($quote->getRemainingDays());

        $summary = $quote->getSummaryMessage();
        // Should not crash even without remaining days
        $this->assertStringContainsString('$15.00', $summary);
    }
}