<?php

namespace App\Tests\Unit\Services\Vouchers\Providers;

use App\DTO\Vouchers\VoucherValidationResult;
use App\Enums\Vouchers\VoucherType;
use App\Services\Vouchers\DiscountContext\DiscountContext;
use App\Services\Vouchers\DiscountContext\VoucherContext;
use App\Services\Vouchers\Providers\VoucherDiscountProvider;
use App\Services\Vouchers\VoucherService;
use Mockery;
use PHPUnit\Framework\TestCase;

class VoucherDiscountProviderTest extends TestCase
{
    private VoucherService $voucherService;

    public function setUp(): void
    {
        $this->voucherService = Mockery::mock(VoucherService::class);
        parent::setUp();
    }

    public function test_priority_is_correct(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $this->assertEquals(30, $provider->priority());
    }

    public function test_supports_returns_false_when_no_voucher_data(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

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
        $provider = new VoucherDiscountProvider($this->voucherService);

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
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherValidation = new VoucherValidationResult(true, 'good');

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            isSubscription: false,
            voucherContext: new VoucherContext(['applies_to' => 'subscription_first_cycle', 'voucher_code' => 'TEST', 'subscription_plan_id' => 1])
        );

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->with('TEST', 1, null, null, null)->andReturn($voucherValidation);

        $this->assertTrue($provider->supports($context));
    }

    public function test_supports_returns_false_for_subscription_when_not_applicable(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

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
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherData = [
            'voucher_code' => 'TEST',
            'discount_type' => VoucherType::Percentage->value,
            'discount' => 20,
            'applies_to' => 'one_time',
            'eligible_items' => [
                ['id' => 1]
            ],
            'is_stackable' => true,
            'order_value' => 1000
        ];

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 1000,
            currentSubtotalCents: 1000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            voucherContext: new VoucherContext($voucherData)
        );

        $validationResult = new VoucherValidationResult(
            valid: true,
            eligibleSubtotal: 1000,
            discount: 20,
            eligibleItems: [1],
            message: 'test'
        );

        $this->voucherService
            ->shouldReceive('validateVoucher')
            ->with('TEST', 1000)
            ->andReturn($validationResult);

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(2000, $result->discountAmountCents);
        $this->assertEquals('voucher', $result->type);
    }


    public function test_apply_calculates_fixed_discount(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherData = [
            'voucher_code' => 'FIXED',
            'discount_type' => 'fixed',
            'discount' => 15, // £15
            'applies_to' => 'one_time',
            'eligible_items' => [
                ['id' => 1]
            ],
            'is_stackable' => true,
            'order_value' => 10000
        ];

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            voucherContext: new VoucherContext($voucherData)
        );

        $validationResult = new VoucherValidationResult(
            valid: true,
            eligibleSubtotal: 100,
            discount: 15,
            eligibleItems: [1],
            message: 'fixed test'
        );

        $this->voucherService
            ->shouldReceive('validateVoucher')
            ->with('FIXED', 10000)
            ->andReturn($validationResult);

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(1500, $result->discountAmountCents);
    }

    public function test_apply_respects_max_discount_cap(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherData = [
            'voucher_code' => 'MAXCAP',
            'discount_type' => VoucherType::Percentage->value,
            'discount' => 50, // 50%
            'applies_to' => 'one_time',
            'eligible_items' => [
                ['id' => 1]
            ],
            'is_stackable' => true,
            'max_discount' => 10, // £10 cap
            'order_value' => 10000
        ];

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            voucherContext: new VoucherContext($voucherData)
        );

        $validationResult = new VoucherValidationResult(
            valid: true,
            eligibleSubtotal: 10000,
            discount: 5000, // 50% of 10000
            eligibleItems: [1],
            message: 'max cap'
        );

        $this->voucherService
            ->shouldReceive('validateVoucher')
            ->with('MAXCAP', 10000)
            ->andReturn($validationResult);

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(1000, $result->discountAmountCents); // capped at £10
    }

    public function test_apply_only_applies_to_eligible_items(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherData = [
            'voucher_code' => 'ELIGIBLE',
            'discount_type' => VoucherType::Percentage->value,
            'discount' => 20,
            'applies_to' => 'one_time',
            'eligible_items' => [
                ['id' => 1]
            ],
            'is_stackable' => true,
            'order_value' => 15000
        ];

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1],
                ['id' => 2, 'price' => 50, 'quantity' => 1],
            ],
            baseSubtotalCents: 15000,
            currentSubtotalCents: 15000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            voucherContext: new VoucherContext($voucherData)
        );

        $validationResult = new VoucherValidationResult(
            valid: true,
            eligibleSubtotal: 100, // only item 1 is eligible
            discount: 20,
            eligibleItems: [1],
            message: 'eligible items'
        );

        $this->voucherService
            ->shouldReceive('validateVoucher')
            ->with('ELIGIBLE', 15000)
            ->andReturn($validationResult);

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(2000, $result->discountAmountCents);
    }

    public function test_apply_determines_merchant_funding_source(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherData = [
            'voucher_code' => 'MERCHANT',
            'discount_type' => VoucherType::Percentage->value,
            'discount' => 20,
            'applies_to' => 'one_time',
            'eligible_items' => [
                ['id' => 1]
            ],
            'is_stackable' => true,
            'merchant_id' => 5,
            'order_value' => 10000
        ];

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            voucherContext: new VoucherContext($voucherData)
        );

        $validationResult = new VoucherValidationResult(
            valid: true,
            eligibleSubtotal: 100,
            discount: 20,
            eligibleItems: [1],
            message: 'merchant funding'
        );

        $this->voucherService
            ->shouldReceive('validateVoucher')
            ->with('MERCHANT', 10000)
            ->andReturn($validationResult);

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals('merchant', $result->fundingSource);
    }


    public function test_apply_defaults_to_platform_funding(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherData = [
            'voucher_code' => 'PLATFORM',
            'discount_type' => VoucherType::Percentage->value,
            'discount' => 20,
            'applies_to' => 'one_time',
            'eligible_items' => [
                ['id' => 1]
            ],
            'is_stackable' => true,
            'order_value' => 10000
        ];

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            voucherContext: new VoucherContext($voucherData)
        );

        $validationResult = new VoucherValidationResult(
            valid: true,
            eligibleSubtotal: 100,
            discount: 20,
            eligibleItems: [1],
            message: 'platform funding'
        );

        $this->voucherService
            ->shouldReceive('validateVoucher')
            ->with('PLATFORM', 10000)
            ->andReturn($validationResult);

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals('platform', $result->fundingSource);
    }

    public function test_apply_returns_null_when_no_eligible_items(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

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

    public function test_apply_returns_null_when_no_voucher_context(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            voucherContext: null // No voucher context
        );

        $result = $provider->apply($context);

        $this->assertNull($result);
    }

    public function test_apply_returns_null_when_voucher_invalid(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherData = [
            'voucher_code' => 'TEST',
            'discount_type' => VoucherType::Percentage->value,
            'discount' => 20,
            'applies_to' => 'one_time',
            'eligible_items' => [['id' => 1]],
            'order_value' => 10000
        ];

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100, 'quantity' => 1]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            voucherContext: new VoucherContext($voucherData)
        );

        $validationResult = new VoucherValidationResult(
            valid: false,
            eligibleSubtotal: 100,
            discount: 20,
            eligibleItems: [1],
            message: 'invalid'
        );

        $this->voucherService
            ->shouldReceive('validateVoucher')
            ->with('TEST', 10000)
            ->andReturn($validationResult);

        $result = $provider->apply($context);

        $this->assertNull($result);
    }

    public function test_apply_for_subscription_first_cycle(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherData = [
            'voucher_code' => 'SUBS_FIRST',
            'discount_type' => VoucherType::Percentage->value,
            'discount' => 50,
            'applies_to' => 'subscription_first_cycle',
            'eligible_items' => [
                ['id' => 1]
            ],
            'is_stackable' => true,
            'subscription_plan_id' => 1,
            'order_value' => 20000
        ];

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 200, 'quantity' => 1]
            ],
            baseSubtotalCents: 20000,
            currentSubtotalCents: 20000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            isSubscription: true,
            voucherContext: new VoucherContext($voucherData)
        );

        $validationResult = new VoucherValidationResult(
            valid: true,
            eligibleSubtotal: 200,
            discount: 100, // expected discount for first cycle
            eligibleItems: [1],
            message: 'subscription first cycle'
        );

        $this->voucherService
            ->shouldReceive('validateVoucherForSubscription')
            ->with('SUBS_FIRST', 1, null, null, null)
            ->andReturn($validationResult);

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(10000, $result->discountAmountCents); // match the validation discount
    }

    public function test_apply_respects_stackable_flag(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherData = [
            'voucher_code' => 'STACK',
            'discount_type' => 'fixed',
            'discount' => 200,
            'applies_to' => 'one_time',
            'eligible_items' => [['id' => 1]],
            'is_stackable' => false,
            'order_value' => 10000
        ];

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100, 'quantity' => 1]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            voucherContext: new VoucherContext($voucherData)
        );

        $validationResult = new VoucherValidationResult(
            valid: true,
            eligibleSubtotal: 100,
            discount: 20,
            eligibleItems: [1],
            message: 'ok'
        );

        $this->voucherService
            ->shouldReceive('validateVoucher')
            ->with('STACK', 10000)
            ->andReturn($validationResult);

        $result = $provider->apply($context);

        $this->assertFalse($result->stackable);
    }

    public function test_apply_returns_null_for_zero_discount(): void
    {
        $provider = new VoucherDiscountProvider($this->voucherService);

        $voucherData = [
            'voucher_code' => 'ZERO',
            'discount_type' => VoucherType::Percentage->value,
            'discount' => 0,
            'applies_to' => 'one_time',
            'eligible_items' => [
                ['id' => 1]
            ],
            'is_stackable' => true,
            'order_value' => 10000
        ];

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            voucherContext: new VoucherContext($voucherData)
        );

        $validationResult = new VoucherValidationResult(
            valid: true,
            eligibleSubtotal: 100,
            discount: 0, // zero discount
            eligibleItems: [1],
            message: 'no discount'
        );

        $this->voucherService
            ->shouldReceive('validateVoucher')
            ->with('ZERO', 10000)
            ->andReturn($validationResult);

        $result = $provider->apply($context);

        $this->assertNull($result); // should return null when discount is 0
    }
}