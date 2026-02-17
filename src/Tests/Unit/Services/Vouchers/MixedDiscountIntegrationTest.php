<?php

namespace App\Tests\Unit\Services\Vouchers;

use App\Models\Member;
use App\Repositories\Offers\TieredPromotionRepository;
use App\Services\Vouchers\DiscountContext\DiscountContext;
use App\Services\Vouchers\DiscountProviderRegistry;
use App\Services\Vouchers\DiscountResolver;
use App\Services\Vouchers\Providers\OfferDiscountProvider;
use App\Services\Vouchers\Providers\TieredDiscountProvider;
use App\Services\Vouchers\Providers\VoucherDiscountProvider;
use Mockery;
use PHPUnit\Framework\TestCase;

class MixedDiscountIntegrationTest extends TestCase
{
    public function test_offer_then_tiered_then_voucher_stack_correctly(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        // Setup: $100 item with $20 offer discount = $80 post-offer
        $items = [
            [
                'id' => 1,
                'price' => 80,
                'base_price' => 100,
                'quantity' => 1
            ]
        ];

        // Tiered: 10% off $80+ orders
        $tieredPromotion = Mockery::mock(\App\Models\TieredPromotion::class)->makePartial();
        $tieredPromotion->id = 1;
        $tieredPromotion->name = '10% off $80+';
        $tieredPromotion->min_subtotal_cents = 8000;
        $tieredPromotion->discount_type = 'percentage';
        $tieredPromotion->value = 10;
        $tieredPromotion->stackable = true;
        $tieredPromotion->applies_to = 'one_time';

        $tieredRepo = Mockery::mock(TieredPromotionRepository::class);
        $tieredRepo->shouldReceive('findApplicablePromotion')
            ->andReturn($tieredPromotion);

        // Voucher: $5 off
        $voucherData = [
            'valid' => true,
            'discount_type' => 'fixed',
            'discount' => 5,
            'is_stackable' => true,
            'eligible_items' => [['id' => 1]]
        ];

        $providers = [
            new OfferDiscountProvider(),
            new TieredDiscountProvider($tieredRepo),
            new VoucherDiscountProvider($voucherData),
        ];

        $discountProviderRegistry = new DiscountProviderRegistry();
        $discountProviderRegistry->setProviders($providers);

        $resolver = new DiscountResolver($discountProviderRegistry);

        $context = new DiscountContext(
            items: $items,
            baseSubtotalCents: 10000, // $100
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            isSubscription: false,
            siteId: 1
        );

        $result = $resolver->resolve($context);

        // Offer: $20 (100 - 80)
        $this->assertEquals(2000, $result->offerDiscountCents);

        // Tiered: 10% of $80 = $8
        $this->assertEquals(800, $result->tieredDiscountCents);

        // Voucher: $5
        $this->assertEquals(500, $result->voucherDiscountCents);

        // Total discount: $33
        $this->assertEquals(3300, $result->getTotalDiscountCents());

        // Final: $100 - $33 = $67
        $this->assertEquals(6700, $result->finalSubtotalCents);
    }

    public function test_non_stackable_voucher_overrides_previous_discounts(): void
    {
        $items = [
            ['id' => 1, 'price' => 80, 'base_price' => 100, 'quantity' => 1]
        ];

        $voucherData = [
            'valid' => true,
            'discount_type' => 'fixed',
            'discount' => 30,
            'is_stackable' => false,
            'eligible_items' => [['id' => 1]]
        ];

        $providers = [
            new OfferDiscountProvider(),
            new VoucherDiscountProvider($voucherData),
        ];

        $discountProviderRegistry = new DiscountProviderRegistry();
        $discountProviderRegistry->setProviders($providers);

        $resolver = new DiscountResolver($discountProviderRegistry);

        $context = new DiscountContext(
            items: $items,
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $resolver->resolve($context);

        // Non-stackable voucher should override offer
        $this->assertEquals(0, $result->offerDiscountCents);
        $this->assertEquals(3000, $result->voucherDiscountCents);
        $this->assertEquals(7000, $result->finalSubtotalCents);
    }

    public function test_subscription_first_cycle_discount_tracking(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $items = [
            ['id' => 1, 'price' => 50, 'subscription_plan_id' => 1, 'quantity' => 1]
        ];

        $voucherData = [
            'valid' => true,
            'discount_type' => 'percentage',
            'discount' => 20,
            'is_stackable' => true,
            'applies_to' => 'subscription_first_cycle',
            'eligible_items' => [['id' => 1]]
        ];

        $providers = [
            new VoucherDiscountProvider($voucherData),
        ];

        $discountProviderRegistry = new DiscountProviderRegistry();
        $discountProviderRegistry->setProviders($providers);

        $resolver = new DiscountResolver($discountProviderRegistry);

        $context = new DiscountContext(
            items: $items,
            baseSubtotalCents: 5000,
            currentSubtotalCents: 5000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            isSubscription: true,
            isFirstSubscriptionCycle: true,
            siteId: 1
        );

        $result = $resolver->resolve($context);

        $this->assertEquals(1000, $result->voucherDiscountCents);
        $this->assertEquals(4000, $result->finalSubtotalCents);

        // Verify metadata for Stripe
        $metadata = $result->getStripeMetadata();
        $this->assertEquals(10, $metadata['first_cycle_discount_total']);
        $this->assertEquals(0, $metadata['recurring_discount_total']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}