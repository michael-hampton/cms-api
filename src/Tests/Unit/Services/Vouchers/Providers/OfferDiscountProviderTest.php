<?php

namespace App\Tests\Unit\Services\Vouchers\Providers;

use App\Services\Vouchers\DiscountContext;
use App\Services\Vouchers\Providers\OfferDiscountProvider;
use PHPUnit\Framework\TestCase;

class OfferDiscountProviderTest extends TestCase
{
    public function test_priority_is_correct(): void
    {
        $provider = new OfferDiscountProvider();

        $this->assertEquals(10, $provider->priority());
    }

    public function test_supports_returns_true_when_items_have_offers(): void
    {
        $provider = new OfferDiscountProvider();

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 80, 'base_price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $this->assertTrue($provider->supports($context));
    }

    public function test_supports_returns_false_when_no_offers(): void
    {
        $provider = new OfferDiscountProvider();

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $this->assertFalse($provider->supports($context));
    }

    public function test_apply_calculates_offer_discount_correctly(): void
    {
        $provider = new OfferDiscountProvider();

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 80, 'base_price' => 100, 'quantity' => 2]
            ],
            baseSubtotalCents: 20000,
            currentSubtotalCents: 20000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(4000, $result->discountAmountCents); // (100-80) * 2 * 100
        $this->assertEquals([1], $result->affectedItemIds);
        $this->assertEquals('merchant', $result->fundingSource);
        $this->assertTrue($result->stackable);
    }

    public function test_apply_handles_item_type_offer(): void
    {
        $provider = new OfferDiscountProvider();

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 50, 'item_type' => 'offer', 'quantity' => 1, 'base_price' => 100]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(5000, $result->discountAmountCents);
    }

    public function test_apply_returns_null_when_no_discount(): void
    {
        $provider = new OfferDiscountProvider();

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $provider->apply($context);

        $this->assertNull($result);
    }

    public function test_apply_handles_multiple_offer_items(): void
    {
        $provider = new OfferDiscountProvider();

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 80, 'base_price' => 100, 'quantity' => 1],
                ['id' => 2, 'price' => 45, 'base_price' => 50, 'quantity' => 2],
            ],
            baseSubtotalCents: 20000,
            currentSubtotalCents: 20000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $provider->apply($context);

        // (100-80)*1 + (50-45)*2 = 20 + 10 = 30
        $this->assertEquals(3000, $result->discountAmountCents);
        $this->assertCount(2, $result->affectedItemIds);
    }
}