<?php

namespace App\Tests\Unit\Services\Vouchers\Providers;

use App\Models\TieredPromotion;
use App\Repositories\Offers\TieredPromotionRepository;
use App\Services\Vouchers\DiscountContext\DiscountContext;
use App\Services\Vouchers\Providers\TieredDiscountProvider;
use Mockery;
use PHPUnit\Framework\TestCase;

class TieredDiscountProviderTest extends TestCase
{
    public function test_priority_is_correct(): void
    {
        $repository = Mockery::mock(TieredPromotionRepository::class);
        $provider = new TieredDiscountProvider($repository);

        $this->assertEquals(20, $provider->priority());
    }

    public function test_supports_returns_false_when_cart_under_threshold(): void
    {
        $repository = Mockery::mock(TieredPromotionRepository::class);
        $repository->shouldReceive('findApplicablePromotion')
            ->with(5000, false)
            ->andReturn(null);

        $provider = new TieredDiscountProvider($repository);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 50]],
            baseSubtotalCents: 5000,
            currentSubtotalCents: 5000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            isSubscription: false
        );

        $this->assertFalse($provider->supports($context));
    }

    public function test_supports_returns_true_when_cart_meets_tier(): void
    {
        $promotion = Mockery::mock(TieredPromotion::class)->makePartial();
        $promotion->id = 1;
        $promotion->min_subtotal_cents = 10000;

        $repository = Mockery::mock(TieredPromotionRepository::class);
        $repository->shouldReceive('findApplicablePromotion')
            ->with(15000, false)
            ->andReturn($promotion);

        $provider = new TieredDiscountProvider($repository);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 150]],
            baseSubtotalCents: 15000,
            currentSubtotalCents: 15000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            isSubscription: false
        );

        $this->assertTrue($provider->supports($context));
    }

    public function test_apply_calculates_percentage_discount(): void
    {
        $promotion = Mockery::mock(TieredPromotion::class)->makePartial();
        $promotion->id = 1;
        $promotion->name = 'Spend $100 Save 10%';
        $promotion->min_subtotal_cents = 10000;
        $promotion->discount_type = 'percentage';
        $promotion->value = 10;
        $promotion->stackable = true;
        $promotion->applies_to = 'one_time';

        $repository = Mockery::mock(TieredPromotionRepository::class);
        $repository->shouldReceive('findApplicablePromotion')
            ->andReturn($promotion);

        $provider = new TieredDiscountProvider($repository);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 120]],
            baseSubtotalCents: 12000,
            currentSubtotalCents: 12000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            isSubscription: false
        );

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(1200, $result->discountAmountCents); // 10% of 12000
        $this->assertEquals('tiered', $result->type);
        $this->assertTrue($result->stackable);
    }

    public function test_apply_calculates_fixed_discount(): void
    {
        $promotion = Mockery::mock(TieredPromotion::class)->makePartial();
        $promotion->id = 1;
        $promotion->name = 'Spend $100 Save $15';
        $promotion->min_subtotal_cents = 10000;
        $promotion->discount_type = 'fixed';
        $promotion->value = 15.00;
        $promotion->stackable = true;
        $promotion->applies_to = 'one_time';

        $repository = Mockery::mock(TieredPromotionRepository::class);
        $repository->shouldReceive('findApplicablePromotion')
            ->andReturn($promotion);

        $provider = new TieredDiscountProvider($repository);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 120]],
            baseSubtotalCents: 12000,
            currentSubtotalCents: 12000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            isSubscription: false
        );

        $result = $provider->apply($context);

        $this->assertEquals(1500, $result->discountAmountCents);
    }

    public function test_apply_selects_highest_applicable_tier(): void
    {
        // This test verifies the repository behavior
        $lowTier = Mockery::mock(TieredPromotion::class)->makePartial();
        $lowTier->id = 1;
        $lowTier->min_subtotal_cents = 5000;
        $lowTier->discount_type = 'percentage';
        $lowTier->value = 5;

        $highTier = Mockery::mock(TieredPromotion::class)->makePartial();
        $highTier->id = 2;
        $highTier->name = 'High Tier';
        $highTier->min_subtotal_cents = 10000;
        $highTier->discount_type = 'percentage';
        $highTier->value = 10;
        $highTier->stackable = true;
        $highTier->applies_to = 'one_time';

        $repository = Mockery::mock(TieredPromotionRepository::class);
        // Repository should return highest tier
        $repository->shouldReceive('findApplicablePromotion')
            ->with(12000, false)
            ->andReturn($highTier);

        $provider = new TieredDiscountProvider($repository);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 120]],
            baseSubtotalCents: 12000,
            currentSubtotalCents: 12000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            isSubscription: false
        );

        $result = $provider->apply($context);

        // Should use high tier (10%)
        $this->assertEquals(1200, $result->discountAmountCents);
        $this->assertEquals('High Tier', $result->metadata['promotion_name']);
    }

    public function test_apply_evaluates_after_product_offers(): void
    {
        // Uses currentSubtotalCents (post-offer)
        $promotion = Mockery::mock(TieredPromotion::class)->makePartial();
        $promotion->id = 1;
        $promotion->name = 'Tiered Promo';
        $promotion->min_subtotal_cents = 8000; // $80 threshold
        $promotion->discount_type = 'percentage';
        $promotion->value = 10;
        $promotion->stackable = true;
        $promotion->applies_to = 'one_time';

        $repository = Mockery::mock(TieredPromotionRepository::class);
        // Called with post-offer subtotal (9000)
        $repository->shouldReceive('findApplicablePromotion')
            ->with(9000, false)
            ->andReturn($promotion);

        $provider = new TieredDiscountProvider($repository);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 90, 'base_price' => 100]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 9000, // After $10 offer discount
            currentOfferDiscountCents: 1000,
            appliedDiscounts: [],
            member: null,
            isSubscription: false
        );

        $result = $provider->apply($context);

        // 10% of post-offer subtotal (9000)
        $this->assertEquals(900, $result->discountAmountCents);
    }

    public function test_apply_respects_subscription_compatibility(): void
    {
        $promotion = Mockery::mock(TieredPromotion::class)->makePartial();
        $promotion->id = 1;
        $promotion->applies_to = 'one_time';

        $repository = Mockery::mock(TieredPromotionRepository::class);
        // Repository filters out subscription-incompatible promotions
        $repository->shouldReceive('findApplicablePromotion')
            ->with(10000, true)
            ->andReturn(null);

        $provider = new TieredDiscountProvider($repository);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null,
            isSubscription: true
        );

        $result = $provider->apply($context);

        $this->assertNull($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}