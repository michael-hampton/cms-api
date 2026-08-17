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
use InvalidArgumentException;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class SubscriptionPricingResolverTest extends TestCase
{
    private SubscriptionPlanPricingRepository $pricingRepository;
    private VoucherService $voucherService;
    private SubscriptionPricingResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingRepository = m::mock(SubscriptionPlanPricingRepository::class);
        $this->voucherService    = m::mock(VoucherService::class);

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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Stub findActivePromotionForPlan to return null (no promotion on plan).
     * Must be called for every test that passes no voucher_code, because
     * Step 2a runs whenever $voucherId is null after the code-based lookup.
     */
    private function expectNoPromotion(int $planId): void
    {
        $this->voucherService
            ->shouldReceive('findActivePromotionForPlan')
            ->with($planId)
            ->once()
            ->andReturn(null);
    }

    private function makePromoVoucher(int $id, float $discountAmount): Voucher
    {
        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = $id;
        $voucher->shouldReceive('calculateSubscriptionDiscount')
            ->andReturn($discountAmount);

        return $voucher;
    }

    // -------------------------------------------------------------------------
    // Existing tests — updated to expect findActivePromotionForPlan(null path)
    // -------------------------------------------------------------------------

    public function testResolveFallbackToPlanPriceWhenNoPricingTier(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 29.99;
        $plan->currency = 'USD';

        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->with(1)
            ->once()
            ->andReturn(null);

        $this->expectNoPromotion(1);

        $resolved = $this->resolver->resolve($plan, ['variant' => SubscriptionType::PRINTED->value], 1);

        $this->assertInstanceOf(ResolvedSubscriptionPrice::class, $resolved);
        $this->assertNull($resolved->pricingTierId);
        $this->assertEquals(29.99, $resolved->basePrice);
        $this->assertEquals(29.99, $resolved->finalPrice);
        $this->assertEquals(SubscriptionType::PRINTED->value, $resolved->variant);
        $this->assertEquals('USD', $resolved->currency);
        $this->assertEquals(0, $resolved->discountAmount);
        $this->assertNull($resolved->voucherId);
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

        $this->expectNoPromotion(1);

        $resolved = $this->resolver->resolve($plan, ['variant' => SubscriptionType::PRINTED->value], 1);

        $this->assertEquals(5, $resolved->pricingTierId);
        $this->assertEquals(39.99, $resolved->basePrice);
        $this->assertEquals(39.99, $resolved->finalPrice);
        $this->assertNull($resolved->voucherId);
    }

    public function testResolveMatchesTierByDurationAndIssueCountFromCartOptions(): void
    {
        // Regression coverage: findMatchingTier() was fully implemented
        // but never called anywhere — resolve() always skipped straight to
        // getDefaultForPlan() even when the caller supplied duration_months
        // / issue_count (e.g. from stored cart item options). It's now
        // wired in as the first fallback, ahead of the plan default.
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 29.99;
        $plan->currency = 'USD';

        $matchedTier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $matchedTier->id = 9;
        $matchedTier->plan_id = 1;
        $matchedTier->is_active = true;
        $matchedTier->price = 49.99;
        $matchedTier->sale_price = null;
        $matchedTier->digital_price = null;
        $matchedTier->digital_sale_price = null;

        $query = m::mock(\App\Framework\Database\QueryBuilder::class);
        $query->shouldReceive('where')->once()->with('duration_months', 12)->andReturnSelf();
        $query->shouldReceive('where')->once()->with('issue_count', 26)->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn($matchedTier);

        $this->pricingRepository->shouldReceive('getActiveTiersForPlan')
            ->once()
            ->with(1)
            ->andReturn($query);

        $this->pricingRepository->shouldNotReceive('getDefaultForPlan');

        $this->expectNoPromotion(1);

        $resolved = $this->resolver->resolve($plan, [
            'variant' => SubscriptionType::PRINTED->value,
            'duration_months' => 12,
            'issue_count' => 26,
        ], 1);

        $this->assertEquals(9, $resolved->pricingTierId);
        $this->assertEquals(49.99, $resolved->basePrice);
    }

    public function testResolveFallsBackToDefaultTierWhenNoMatchingTierFound(): void
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

        $query = m::mock(\App\Framework\Database\QueryBuilder::class);
        $query->shouldReceive('where')->once()->with('duration_months', 3)->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn(null);

        $this->pricingRepository->shouldReceive('getActiveTiersForPlan')
            ->once()
            ->with(1)
            ->andReturn($query);

        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->once()
            ->with(1)
            ->andReturn($defaultTier);

        $this->expectNoPromotion(1);

        $resolved = $this->resolver->resolve($plan, [
            'variant' => SubscriptionType::PRINTED->value,
            'duration_months' => 3,
        ], 1);

        $this->assertEquals(5, $resolved->pricingTierId);
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

        $this->expectNoPromotion(1);

        $resolved = $this->resolver->resolve(
            $plan,
            ['pricing_tier_id' => 7, 'variant' => SubscriptionType::PRINTED->value],
            1
        );

        $this->assertEquals(7, $resolved->pricingTierId);
        $this->assertEquals(49.99, $resolved->basePrice);
        $this->assertEquals(44.99, $resolved->salePrice);
        $this->assertEquals(44.99, $resolved->finalPrice);
        $this->assertTrue($resolved->hasSalePrice());
        $this->assertNull($resolved->voucherId);
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

        $this->expectNoPromotion(1);

        $resolved = $this->resolver->resolve(
            $plan,
            ['pricing_tier_id' => 3, 'variant' => SubscriptionType::DIGITAL->value],
            1
        );

        $this->assertEquals(SubscriptionType::DIGITAL->value, $resolved->variant);
        $this->assertEquals(29.99, $resolved->basePrice);
        $this->assertEquals(24.99, $resolved->salePrice);
        $this->assertEquals(24.99, $resolved->finalPrice);
        $this->assertNull($resolved->voucherId);
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
        $tier->digital_price = null;
        $tier->digital_sale_price = null;

        $this->pricingRepository->shouldReceive('find')
            ->with(3)
            ->once()
            ->andReturn($tier);

        $this->expectNoPromotion(1);

        $resolved = $this->resolver->resolve(
            $plan,
            ['pricing_tier_id' => 3, 'variant' => SubscriptionType::DIGITAL->value],
            1
        );

        $this->assertEquals(SubscriptionType::DIGITAL->value, $resolved->variant);
        $this->assertEquals(49.99, $resolved->basePrice);
        $this->assertNull($resolved->salePrice);
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

        // User entered a code — promotion lookup must NOT run (voucherId already set)
        $this->voucherService->shouldNotReceive('findActivePromotionForPlan');

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
            ->with('SAVE5', 1, 1, null, SubscriptionType::PRINTED->value)
            ->once()
            ->andReturn($voucherValidation);

        $resolved = $this->resolver->resolve(
            $plan,
            ['variant' => SubscriptionType::PRINTED->value, 'voucher_code' => 'SAVE5'],
            1
        );

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

        // Validation throws before promotion lookup is reached
        $this->voucherService->shouldNotReceive('findActivePromotionForPlan');

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
            ->with('EXPIRED', 1, 1, null, SubscriptionType::PRINTED->value)
            ->once()
            ->andReturn($voucherValidation);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Voucher expired');

        $this->resolver->resolve(
            $plan,
            ['variant' => SubscriptionType::PRINTED->value, 'voucher_code' => 'EXPIRED'],
            1
        );
    }

    public function testResolveThrowsOnInvalidVariant(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid variant: audio');

        $this->resolver->resolve($plan, ['variant' => 'audio'], 1);
    }

    public function testResolveThrowsOnInactivePricingTier(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 5;
        $tier->is_active = false;

        $this->pricingRepository->shouldReceive('find')
            ->with(5)
            ->once()
            ->andReturn($tier);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or inactive pricing tier: 5');

        $this->resolver->resolve(
            $plan,
            ['pricing_tier_id' => 5, 'variant' => SubscriptionType::PRINTED->value],
            1
        );
    }

    public function testResolveThrowsOnMismatchedPlanId(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 5;
        $tier->plan_id = 2;
        $tier->is_active = true;

        $this->pricingRepository->shouldReceive('find')
            ->with(5)
            ->once()
            ->andReturn($tier);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pricing tier 5 does not belong to plan 1');

        $this->resolver->resolve(
            $plan,
            ['pricing_tier_id' => 5, 'variant' => SubscriptionType::PRINTED->value],
            1
        );
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
        $tier->sale_price = 80.00;
        $tier->digital_price = null;

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = 10;

        $this->pricingRepository->shouldReceive('find')
            ->with(7)
            ->once()
            ->andReturn($tier);

        // User entered a code — promotion lookup must NOT run
        $this->voucherService->shouldNotReceive('findActivePromotionForPlan');

        $voucherValidation = new VoucherValidationResult(
            valid: true,
            message: 'Voucher applied',
            discount: 10.00,
            voucher: $voucher,
            finalPrice: 70.00
        );

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->once()
            ->andReturn($voucherValidation);

        $resolved = $this->resolver->resolve(
            $plan,
            ['pricing_tier_id' => 7, 'variant' => SubscriptionType::PRINTED->value, 'voucher_code' => 'SAVE10'],
            1
        );

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

        $this->voucherService->shouldReceive('findActivePromotionForPlan')
            ->twice()
            ->andReturn(null);

        $items = [
            ['plan' => $plan1, 'data' => ['variant' => SubscriptionType::PRINTED->value], 'member_id' => 1],
            ['plan' => $plan2, 'data' => ['variant' => SubscriptionType::DIGITAL->value], 'member_id' => 1],
        ];

        $results = $this->resolver->resolveBatch($items);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(ResolvedSubscriptionPrice::class, $results[0]);
        $this->assertInstanceOf(ResolvedSubscriptionPrice::class, $results[1]);
        $this->assertEquals(29.99, $results[0]->finalPrice);
        $this->assertEquals(39.99, $results[1]->finalPrice);
    }

    // -------------------------------------------------------------------------
    // New tests — plan promotion path (Step 2a)
    // -------------------------------------------------------------------------

    public function testResolveAppliesPlanPromotionWhenNoVoucherCodeProvided(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 50.00;
        $plan->currency = 'GBP';

        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->with(1)
            ->once()
            ->andReturn(null);

        $promotion = $this->makePromoVoucher(id: 99, discountAmount: 10.00);

        $this->voucherService->shouldReceive('findActivePromotionForPlan')
            ->with(1)
            ->once()
            ->andReturn($promotion);

        $resolved = $this->resolver->resolve(
            $plan,
            ['variant' => SubscriptionType::PRINTED->value],
            memberId: 5
        );

        // Discount is applied and voucher_id is persisted
        $this->assertEquals(10.00, $resolved->discountAmount);
        $this->assertEquals(40.00, $resolved->finalPrice);
        $this->assertEquals(99, $resolved->voucherId);
        $this->assertTrue($resolved->hasVoucherDiscount());
    }

    public function testResolveAppliesPlanPromotionAgainstSalePriceWhenTierHasSale(): void
    {
        // When a tier has a sale price the promotion discount must be calculated
        // against the effective (sale) price, not the base price.
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 2;
        $plan->currency = 'GBP';

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 10;
        $tier->plan_id = 2;
        $tier->is_active = true;
        $tier->price = 100.00;
        $tier->sale_price = 80.00;
        $tier->digital_price = null;

        $this->pricingRepository->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn($tier);

        // Promotion is configured to give £8 off (10% of the £80 sale price)
        $promotion = $this->makePromoVoucher(id: 55, discountAmount: 8.00);

        $this->voucherService->shouldReceive('findActivePromotionForPlan')
            ->with(2)
            ->once()
            ->andReturn($promotion);

        $resolved = $this->resolver->resolve(
            $plan,
            ['pricing_tier_id' => 10, 'variant' => SubscriptionType::PRINTED->value],
            memberId: 5
        );

        $this->assertEquals(100.00, $resolved->basePrice);
        $this->assertEquals(80.00, $resolved->salePrice);
        $this->assertEquals(8.00, $resolved->discountAmount);
        $this->assertEquals(72.00, $resolved->finalPrice);
        $this->assertEquals(55, $resolved->voucherId);
    }

    public function testResolveAppliesPlanPromotionAgainstBasePriceWhenNoSalePrice(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 3;
        $plan->currency = 'GBP';

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 20;
        $tier->plan_id = 3;
        $tier->is_active = true;
        $tier->price = 60.00;
        $tier->sale_price = null;
        $tier->digital_price = null;

        $this->pricingRepository->shouldReceive('find')
            ->with(20)
            ->once()
            ->andReturn($tier);

        // Promotion gives £6 off the base price of £60
        $promotion = $this->makePromoVoucher(id: 77, discountAmount: 6.00);

        $this->voucherService->shouldReceive('findActivePromotionForPlan')
            ->with(3)
            ->once()
            ->andReturn($promotion);

        $resolved = $this->resolver->resolve(
            $plan,
            ['pricing_tier_id' => 20, 'variant' => SubscriptionType::PRINTED->value],
            memberId: 5
        );

        $this->assertEquals(60.00, $resolved->basePrice);
        $this->assertNull($resolved->salePrice);
        $this->assertEquals(6.00, $resolved->discountAmount);
        $this->assertEquals(54.00, $resolved->finalPrice);
        $this->assertEquals(77, $resolved->voucherId);
    }

    public function testResolveUserVoucherCodeTakesPrecedenceOverPlanPromotion(): void
    {
        // When a user explicitly enters a voucher code the plan promotion must
        // NOT be applied — the user code wins and findActivePromotionForPlan
        // must never be called.
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 50.00;
        $plan->currency = 'GBP';

        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->with(1)
            ->once()
            ->andReturn(null);

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = 42;

        $voucherValidation = new VoucherValidationResult(
            valid: true,
            message: 'Voucher applied',
            discount: 15.00,
            voucher: $voucher,
            finalPrice: 35.00,
            eligibleSubtotal: 50.00,
            isStackable: false,
            eligibleItems: [],
            requiresOverrideDecision: false
        );

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->with('MYCODE', 1, 5, null, SubscriptionType::PRINTED->value)
            ->once()
            ->andReturn($voucherValidation);

        // Promotion lookup must not be called — user code already set voucherId
        $this->voucherService->shouldNotReceive('findActivePromotionForPlan');

        $resolved = $this->resolver->resolve(
            $plan,
            ['variant' => SubscriptionType::PRINTED->value, 'voucher_code' => 'MYCODE'],
            memberId: 5
        );

        $this->assertEquals(42, $resolved->voucherId);
        $this->assertEquals(15.00, $resolved->discountAmount);
        $this->assertEquals(35.00, $resolved->finalPrice);
    }

    public function testResolveAppliesNoDiscountWhenNeitherVoucherNorPromotionExists(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 29.99;
        $plan->currency = 'USD';

        $this->pricingRepository->shouldReceive('getDefaultForPlan')
            ->with(1)
            ->once()
            ->andReturn(null);

        $this->voucherService->shouldReceive('findActivePromotionForPlan')
            ->with(1)
            ->once()
            ->andReturn(null);

        $resolved = $this->resolver->resolve(
            $plan,
            ['variant' => SubscriptionType::PRINTED->value],
            memberId: 1
        );

        $this->assertNull($resolved->voucherId);
        $this->assertEquals(0, $resolved->discountAmount);
        $this->assertEquals(29.99, $resolved->finalPrice);
        $this->assertFalse($resolved->hasVoucherDiscount());
    }

    public function testResolvePromotionAppliedForDigitalVariantUsesDigitalEffectivePrice(): void
    {
        // The promotion discount is calculated against the effective price for
        // the chosen variant — digital sale price when present.
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 4;
        $plan->currency = 'GBP';

        $tier = m::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 30;
        $tier->plan_id = 4;
        $tier->is_active = true;
        $tier->price = 80.00;
        $tier->sale_price = null;
        $tier->digital_price = 40.00;
        $tier->digital_sale_price = 35.00;

        $this->pricingRepository->shouldReceive('find')
            ->with(30)
            ->once()
            ->andReturn($tier);

        // Promotion gives £3.50 off (10% of the £35.00 digital sale price)
        $promotion = $this->makePromoVoucher(id: 88, discountAmount: 3.50);

        $this->voucherService->shouldReceive('findActivePromotionForPlan')
            ->with(4)
            ->once()
            ->andReturn($promotion);

        $resolved = $this->resolver->resolve(
            $plan,
            ['pricing_tier_id' => 30, 'variant' => SubscriptionType::DIGITAL->value],
            memberId: 5
        );

        $this->assertEquals(SubscriptionType::DIGITAL->value, $resolved->variant);
        $this->assertEquals(40.00, $resolved->basePrice);
        $this->assertEquals(35.00, $resolved->salePrice);
        $this->assertEquals(3.50, $resolved->discountAmount);
        $this->assertEquals(88, $resolved->voucherId);
    }
}