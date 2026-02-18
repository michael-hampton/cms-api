<?php

namespace App\Tests\Unit\Services\Subscriptions\Calculators;

use App\DTO\Subscriptions\ResolvedSubscriptionPrice;
use App\DTO\Vouchers\VoucherValidationResult;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Models\Voucher;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Services\Subscriptions\Calculators\SubscriptionPricingResolver;
use App\Services\Vouchers\VoucherService;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class SubscriptionPricingResolverTest extends TestCase
{
    private $pricingRepository;
    private $voucherService;
    private $resolver;

    public function testResolveFallbackToPlanPriceWhenNoPricingTier(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 29.99;
        $plan->currency = 'USD';

        // No pricing tier specified
        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->with(1)
            ->once()
            ->andReturn(null);

        $data = ['variant' => SubscriptionType::PRINTED->value];

        $resolved = $this->resolver->resolve($plan, $data, 1);

        $this->assertInstanceOf(ResolvedSubscriptionPrice::class, $resolved);
        $this->assertNull($resolved->pricingTierId);
        $this->assertEquals(29.99, $resolved->basePrice);
        $this->assertEquals(29.99, $resolved->finalPrice);
        $this->assertEquals(SubscriptionType::PRINTED->value, $resolved->variant);
        $this->assertEquals('USD', $resolved->currency);
        $this->assertEquals(0, $resolved->discountAmount);
    }

    public function testResolveUsesDefaultPricingTierWhenNoTierSpecified(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 29.99;
        $plan->currency = 'USD';

        $defaultTier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $defaultTier->id = 5;
        $defaultTier->plan_id = 1;
        $defaultTier->is_active = true;
        $defaultTier->price = 39.99;
        $defaultTier->sale_price = null;
        $defaultTier->digital_price = null;
        $defaultTier->digital_sale_price = null;

        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->with(1)
            ->once()
            ->andReturn($defaultTier);

        $data = ['variant' => SubscriptionType::PRINTED->value];

        $resolved = $this->resolver->resolve($plan, $data, 1);

        $this->assertEquals(5, $resolved->pricingTierId);
        $this->assertEquals(39.99, $resolved->basePrice);
        $this->assertEquals(39.99, $resolved->finalPrice);
    }

    public function testResolveUsesSpecificPricingTier(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->currency = 'USD';

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 7;
        $tier->plan_id = 1;
        $tier->is_active = true;
        $tier->price = 49.99;
        $tier->sale_price = 44.99;
        $tier->digital_price = null;

        $this->pricingRepository->shouldReceive('find')
            ->with(7)
            ->once()
            ->andReturn($tier);

        $data = [
            'pricing_tier_id' => 7,
            'variant' => SubscriptionType::PRINTED->value
        ];

        $resolved = $this->resolver->resolve($plan, $data, 1);

        $this->assertEquals(7, $resolved->pricingTierId);
        $this->assertEquals(49.99, $resolved->basePrice);
        $this->assertEquals(44.99, $resolved->salePrice);
        $this->assertEquals(44.99, $resolved->finalPrice);
        $this->assertTrue($resolved->hasSalePrice());
    }

    public function testResolveDigitalVariantUsesDigitalPrice(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->currency = 'USD';

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 3;
        $tier->plan_id = 1;
        $tier->is_active = true;
        $tier->price = 49.99;
        $tier->sale_price = null;
        $tier->digital_price = 29.99;
        $tier->digital_sale_price = 24.99;

        $this->pricingRepository->shouldReceive('find')
            ->with(3)
            ->once()
            ->andReturn($tier);

        $data = [
            'pricing_tier_id' => 3,
            'variant' => SubscriptionType::DIGITAL->value
        ];

        $resolved = $this->resolver->resolve($plan, $data, 1);

        $this->assertEquals(SubscriptionType::DIGITAL->value, $resolved->variant);
        $this->assertEquals(49.99, $resolved->basePrice);
        $this->assertEquals(24.99, $resolved->salePrice);
        $this->assertEquals(24.99, $resolved->finalPrice);
    }

    public function testResolveDigitalVariantFallbackToPrintPriceWhenNoDigitalPrice(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->currency = 'USD';

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 3;
        $tier->plan_id = 1;
        $tier->is_active = true;
        $tier->price = 49.99;
        $tier->sale_price = 44.99;
        $tier->digital_price = null; // No digital price set
        $tier->digital_sale_price = null;

        $this->pricingRepository->shouldReceive('find')
            ->with(3)
            ->once()
            ->andReturn($tier);

        $data = [
            'pricing_tier_id' => 3,
            'variant' => SubscriptionType::DIGITAL->value
        ];

        $resolved = $this->resolver->resolve($plan, $data, 1);

        $this->assertEquals(SubscriptionType::DIGITAL->value, $resolved->variant);
        $this->assertEquals(49.99, $resolved->basePrice); // Falls back to print price
        $this->assertNull($resolved->salePrice); // No digital sale price
    }

    public function testResolveAppliesVoucherDiscount(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 29.99;
        $plan->currency = 'USD';

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = 10;

        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->with(1)
            ->once()
            ->andReturn(null);

        $voucherValidation = new VoucherValidationResult(
            valid: true,
            message: 'Voucher applied',
            discount: 5.00,
            voucher: $voucher,
            finalPrice: 24.99,
            eligibleSubtotal: 29.99,
            isStackable: false,
            eligibleItems: [],
            requiresOverrideDecision: false
        );

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->with('SAVE5', 1, 1)
            ->once()
            ->andReturn($voucherValidation);

        $data = [
            'variant' => SubscriptionType::PRINTED->value,
            'voucher_code' => 'SAVE5'
        ];

        $resolved = $this->resolver->resolve($plan, $data, 1);

        $this->assertEquals(29.99, $resolved->basePrice);
        $this->assertEquals(24.99, $resolved->finalPrice);
        $this->assertEquals(5.00, $resolved->discountAmount);
        $this->assertEquals(10, $resolved->voucherId);
        $this->assertTrue($resolved->hasVoucherDiscount());
    }

    public function testResolveThrowsOnInvalidVoucher(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 29.99;
        $plan->currency = 'USD';

        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->andReturn(null);

        $voucherValidation = new VoucherValidationResult(
            valid: false,
            message: 'Voucher expired',
            discount: 0.0,
            voucher: null,
            finalPrice: null,
            eligibleSubtotal: 0,
            eligibleItems: [],
            isStackable: false,
            requiresOverrideDecision: false
        );

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->with('EXPIRED', 1, 1)
            ->once()
            ->andReturn($voucherValidation);

        $data = [
            'variant' => SubscriptionType::PRINTED->value,
            'voucher_code' => 'EXPIRED'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Voucher expired');

        $this->resolver->resolve($plan, $data, 1);
    }

    public function testResolveThrowsOnInvalidVariant(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;

        $data = ['variant' => 'audio']; // Invalid variant

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid variant: audio");

        $this->resolver->resolve($plan, $data, 1);
    }

    public function testResolveThrowsOnInactivePricingTier(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 5;
        $tier->is_active = false; // Inactive

        $this->pricingRepository->shouldReceive('find')
            ->with(5)
            ->once()
            ->andReturn($tier);

        $data = [
            'pricing_tier_id' => 5,
            'variant' => SubscriptionType::PRINTED->value
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or inactive pricing tier: 5');

        $this->resolver->resolve($plan, $data, 1);
    }

    public function testResolveThrowsOnMismatchedPlanId(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 5;
        $tier->plan_id = 2; // Different plan
        $tier->is_active = true;

        $this->pricingRepository->shouldReceive('find')
            ->with(5)
            ->once()
            ->andReturn($tier);

        $data = [
            'pricing_tier_id' => 5,
            'variant' => SubscriptionType::PRINTED->value
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pricing tier 5 does not belong to plan 1');

        $this->resolver->resolve($plan, $data, 1);
    }

    public function testResolveCalculatesTotalSavingsCorrectly(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->currency = 'USD';

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 7;
        $tier->plan_id = 1;
        $tier->is_active = true;
        $tier->price = 100.00;
        $tier->sale_price = 80.00; // $20 sale discount
        $tier->digital_price = null;

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = 10;

        $this->pricingRepository->shouldReceive('find')
            ->with(7)
            ->once()
            ->andReturn($tier);

        $voucherValidation = new VoucherValidationResult(
            valid: true,
            message: 'Voucher applied',
            discount: 10.00, // $10 voucher discount
            voucher: $voucher,
            finalPrice: 70.00
        );

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->once()
            ->andReturn($voucherValidation);

        $data = [
            'pricing_tier_id' => 7,
            'variant' => SubscriptionType::PRINTED->value,
            'voucher_code' => 'SAVE10'
        ];

        $resolved = $this->resolver->resolve($plan, $data, 1);

        $this->assertEquals(100.00, $resolved->basePrice);
        $this->assertEquals(80.00, $resolved->salePrice);
        $this->assertEquals(70.00, $resolved->finalPrice);
        $this->assertEquals(10.00, $resolved->discountAmount);
        $this->assertEquals(30.00, $resolved->getTotalSavings()); // $20 sale + $10 voucher
    }

    public function testResolveBatchProcessesMultipleItems(): void
    {
        $plan1 = m::mock(SubscriptionPlan::class)->makePartial();
        $plan1->id = 1;
        $plan1->price = 29.99;
        $plan1->currency = 'USD';

        $plan2 = m::mock(SubscriptionPlan::class)->makePartial();
        $plan2->id = 2;
        $plan2->price = 39.99;
        $plan2->currency = 'USD';

        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->twice()
            ->andReturn(null);

        $items = [
            ['plan' => $plan1, 'data' => ['variant' => SubscriptionType::PRINTED->value], 'member_id' => 1],
            ['plan' => $plan2, 'data' => ['variant' => SubscriptionType::DIGITAL->value], 'member_id' => 1]
        ];

        $results = $this->resolver->resolveBatch($items);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(ResolvedSubscriptionPrice::class, $results[0]);
        $this->assertInstanceOf(ResolvedSubscriptionPrice::class, $results[1]);
        $this->assertEquals(29.99, $results[0]->finalPrice);
        $this->assertEquals(39.99, $results[1]->finalPrice);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingRepository = m::mock(SubscriptionPlanPricingRepository::class);
        $this->voucherService = m::mock(VoucherService::class);

        $this->resolver = new SubscriptionPricingResolver(
            $this->pricingRepository,
            $this->voucherService
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}