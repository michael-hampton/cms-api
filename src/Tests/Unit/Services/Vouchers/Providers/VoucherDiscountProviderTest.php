<?php

namespace App\Tests\Unit\Services\Vouchers\Providers;

use App\Services\Vouchers\DiscountContext;
use App\Services\Vouchers\Providers\VoucherDiscountProvider;
use PHPUnit\Framework\TestCase;

class VoucherDiscountProviderTest extends TestCase
{
    public function test_priority_is_correct(): void
    {
        $provider = new VoucherDiscountProvider();

        $this->assertEquals(30, $provider->priority());
    }

    public function test_supports_returns_false_when_no_voucher_data(): void
    {
        $provider = new VoucherDiscountProvider();

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $this->assertFalse($provider->supports($context));
    }

    public function test_supports_returns_false_when_voucher_invalid(): void
    {
        $provider = new VoucherDiscountProvider(['valid' => false]);

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $this->assertFalse($provider->supports($context));
    }

    public function test_supports_returns_true_for_one_time_purchase(): void
    {
        $provider = new VoucherDiscountProvider([
            'valid' => true,
            'applies_to' => 'one_time',
            'eligible_items' => []
        ]);

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            isSubscription: false
        );

        $this->assertTrue($provider->supports($context));
    }

    public function test_supports_returns_false_for_subscription_when_not_applicable(): void
    {
        $provider = new VoucherDiscountProvider([
            'valid' => true,
            'applies_to' => 'one_time'
        ]);

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            isSubscription: true
        );

        $this->assertFalse($provider->supports($context));
    }

    public function test_apply_calculates_percentage_discount(): void
    {
        $provider = new VoucherDiscountProvider([
            'valid' => true,
            'discount_type' => 'percentage',
            'discount' => 20,
            'eligible_items' => [
                ['id' => 1]
            ]
        ]);

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

        $this->assertNotNull($result);
        $this->assertEquals(2000, $result->discountAmountCents); // 20% of 10000
        $this->assertEquals('voucher', $result->type);
    }

    public function test_apply_calculates_fixed_discount(): void
    {
        $provider = new VoucherDiscountProvider([
            'valid' => true,
            'discount_type' => 'fixed',
            'discount' => 15,
            'eligible_items' => [
                ['id' => 1]
            ]
        ]);

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

        $this->assertNotNull($result);
        $this->assertEquals(1500, $result->discountAmountCents);
    }

    public function test_apply_respects_max_discount_cap(): void
    {
        $provider = new VoucherDiscountProvider([
            'valid' => true,
            'discount_type' => 'percentage',
            'discount' => 50,
            'max_discount' => 10,
            'eligible_items' => [
                ['id' => 1]
            ]
        ]);

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

        // 50% would be 5000, but max is 1000
        $this->assertEquals(1000, $result->discountAmountCents);
    }

    public function test_apply_only_applies_to_eligible_items(): void
    {
        $provider = new VoucherDiscountProvider([
            'valid' => true,
            'discount_type' => 'percentage',
            'discount' => 20,
            'eligible_items' => [
                ['id' => 1]
            ]
        ]);

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1],
                ['id' => 2, 'price' => 50, 'quantity' => 1],
            ],
            baseSubtotalCents: 15000,
            currentSubtotalCents: 15000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $provider->apply($context);

        // Only applies to item 1: 20% of 10000
        $this->assertEquals(2000, $result->discountAmountCents);
    }

    public function test_apply_determines_merchant_funding_source(): void
    {
        $provider = new VoucherDiscountProvider([
            'valid' => true,
            'discount' => 10,
            'merchant_id' => 123,
            'eligible_items' => [
                ['id' => 1]
            ]
        ]);

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

        $this->assertEquals('merchant', $result->fundingSource);
    }

    public function test_apply_defaults_to_platform_funding(): void
    {
        $provider = new VoucherDiscountProvider([
            'valid' => true,
            'discount' => 10,
            'eligible_items' => [
                ['id' => 1]
            ]
        ]);

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

        $this->assertEquals('platform', $result->fundingSource);
    }

    public function test_apply_returns_null_when_no_eligible_items(): void
    {
        $provider = new VoucherDiscountProvider([
            'valid' => true,
            'discount' => 10,
            'eligible_items' => [
                ['id' => 999]
            ]
        ]);

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
}