<?php

namespace App\Tests\Unit\Models;

use App\Models\SubscriptionPlanPricing;
use App\Models\SubscriptionPlan;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class SubscriptionPlanPricingModelTest extends TestCase
{
    // ── resolveEffectivePrice (via getEffectivePrintPrice / getEffectiveDigitalPrice) ──

    public function testGetEffectivePrintPriceReturnsSalePriceWhenValid(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price      = 49.99;
        $tier->sale_price = 39.99;

        $this->assertEquals(39.99, $tier->getEffectivePrintPrice());
    }

    public function testGetEffectivePrintPriceReturnsBasePriceWhenNoSale(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price      = 49.99;
        $tier->sale_price = null;

        $this->assertEquals(49.99, $tier->getEffectivePrintPrice());
    }

    public function testGetEffectivePrintPriceReturnsBasePriceWhenSaleIsZero(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price      = 49.99;
        $tier->sale_price = 0;

        $this->assertEquals(49.99, $tier->getEffectivePrintPrice());
    }

    public function testGetEffectivePrintPriceReturnsBasePriceWhenSaleExceedsBase(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price      = 49.99;
        $tier->sale_price = 59.99; // higher than base — ignore

        $this->assertEquals(49.99, $tier->getEffectivePrintPrice());
    }

    public function testGetEffectiveDigitalPriceReturnsSalePriceWhenValid(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price             = 49.99;
        $tier->sale_price        = null;
        $tier->digital_price     = 29.99;
        $tier->digital_sale_price = 19.99;

        $this->assertEquals(19.99, $tier->getEffectiveDigitalPrice());
    }

    public function testGetEffectiveDigitalPriceFallsBackToPrintPriceWhenNoneSet(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price              = 49.99;
        $tier->sale_price         = 44.99;
        $tier->digital_price      = null;
        $tier->digital_sale_price = null;

        // Falls back to print price; digital sale falls back to print sale
        $this->assertEquals(44.99, $tier->getEffectiveDigitalPrice());
    }

    public function testGetEffectiveDigitalPriceUsesDigitalPriceWithPrintSaleFallback(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price              = 49.99;
        $tier->sale_price         = 39.99;
        $tier->digital_price      = 29.99;
        $tier->digital_sale_price = null; // no digital sale — falls back to sale_price

        // sale_price (39.99) > digital_price (29.99) so it's ignored
        $this->assertEquals(29.99, $tier->getEffectiveDigitalPrice());
    }

    public function testGetEffectivePriceDispatchesToCorrectVariant(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price              = 49.99;
        $tier->sale_price         = null;
        $tier->digital_price      = 29.99;
        $tier->digital_sale_price = null;

        $this->assertEquals(49.99, $tier->getEffectivePrice('print'));
        $this->assertEquals(29.99, $tier->getEffectivePrice('digital'));
    }

    public function testGetStripeBillingPriceForDigitalPlanUsesDigitalSalePrice(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->digital_download_url = 'https://example.com/download.pdf';
        $plan->print_shipping_required = false;

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price = 86.31;
        $tier->sale_price = 14.39;
        $tier->digital_price = 29.99;
        $tier->digital_sale_price = 12.49;

        $this->assertEquals(12.49, $tier->getStripeBillingPriceForPlan($plan));
    }

    public function testGetStripeBillingPriceForDigitalPlanUsesDigitalPriceWhenNoDigitalSale(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->digital_download_url = 'https://example.com/download.pdf';

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price = 86.31;
        $tier->sale_price = 14.39;
        $tier->digital_price = 29.99;
        $tier->digital_sale_price = null;

        $this->assertEquals(29.99, $tier->getStripeBillingPriceForPlan($plan));
    }

    public function testGetStripeBillingPriceForPrintPlanUsesSalePrice(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->digital_download_url = null;
        $plan->print_shipping_required = true;

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price = 86.31;
        $tier->sale_price = 14.39;

        $this->assertEquals(14.39, $tier->getStripeBillingPriceForPlan($plan));
    }

    public function testGetStripeBillingPriceForPrintPlanUsesPriceWhenNoSale(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = true;

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price = 86.31;
        $tier->sale_price = null;

        $this->assertEquals(86.31, $tier->getStripeBillingPriceForPlan($plan));
    }

    // ── getSavingsText ───────────────────────────────────────────────────────

    public function testGetSavingsTextReturnsFormattedStringWhenDiscountSet(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->discount_percentage = 20;

        $this->assertEquals('SAVE 20%', $tier->getSavingsText());
    }

    public function testGetSavingsTextReturnsNullWhenNoDiscount(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->discount_percentage = 0;

        $this->assertNull($tier->getSavingsText());
    }

    // ── hasDiscount ──────────────────────────────────────────────────────────

    public function testHasDiscountReturnsTrueWhenSalePriceBelowBase(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price      = 49.99;
        $tier->sale_price = 39.99;

        $this->assertTrue($tier->hasDiscount());
    }

    public function testHasDiscountReturnsFalseWhenNoSalePrice(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price      = 49.99;
        $tier->sale_price = null;

        $this->assertFalse($tier->hasDiscount());
    }

    public function testHasDiscountReturnsFalseWhenSalePriceEqualsBase(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price      = 49.99;
        $tier->sale_price = 49.99;

        $this->assertFalse($tier->hasDiscount());
    }

    // ── getActualDiscount ────────────────────────────────────────────────────

    public function testGetActualDiscountReturnsCorrectDifference(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price      = 49.99;
        $tier->sale_price = 39.99;

        $this->assertEquals(10.00, $tier->getActualDiscount());
    }

    public function testGetActualDiscountReturnsZeroWhenNoDiscount(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price      = 49.99;
        $tier->sale_price = null;

        $this->assertEquals(0, $tier->getActualDiscount());
    }

    // ── getPricePerIssue ─────────────────────────────────────────────────────

    public function testGetPricePerIssueReturnsCorrectValue(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price       = 48.00;
        $tier->issue_count = 12;

        $this->assertEquals(4.00, $tier->getPricePerIssue());
    }

    public function testGetPricePerIssueReturnsZeroWhenIssueCountIsZero(): void
    {
        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->price       = 48.00;
        $tier->issue_count = 0;

        $this->assertEquals(0, $tier->getPricePerIssue());
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
