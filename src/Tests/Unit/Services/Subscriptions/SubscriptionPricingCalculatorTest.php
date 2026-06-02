<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\ResolvedSubscriptionPrice;
use App\Enums\CartItemType;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Member;
use App\Models\SubscriptionPlan;
use App\Models\Voucher;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shipping\ShippingService;
use App\Services\Subscriptions\Calculators\SubscriptionPricingResolver;
use App\Services\Subscriptions\SubscriptionPricingService;
use App\Services\Vouchers\VoucherService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SubscriptionPricingCalculatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionPricingService $calculator;
    private $planRepository;
    private $voucherService;
    private $shippingService;
    private SubscriptionPricingResolver $pricingResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->voucherService = Mockery::mock(VoucherService::class);
        $this->shippingService = Mockery::mock(ShippingService::class);
        $this->pricingResolver = Mockery::mock(SubscriptionPricingResolver::class);

        $this->calculator = new SubscriptionPricingService(
            $this->planRepository,
            $this->voucherService,
            $this->shippingService,
            $this->pricingResolver
        );
    }

    // -----------------------------------------------------------------------
    // Existing tests — must all pass unchanged
    // -----------------------------------------------------------------------

    public function test_calculate_digital_subscription_no_voucher(): void
    {
        $plan = $this->createMockPlan(50.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($plan);

        $resolvedPrice = ResolvedSubscriptionPrice::fromPlanPrice(
            planPrice: 50.00,
            currency: 'USD',
            variant: SubscriptionType::DIGITAL->value,
            discountAmount: 0,
            voucherId: null
        );

        $this->pricingResolver->shouldReceive('resolve')
            ->once()
            ->with($plan, ['variant' => SubscriptionType::DIGITAL->value, 'pricing_tier_id' => null, 'voucher_code' => null], 123)
            ->andReturn($resolvedPrice);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => SubscriptionType::DIGITAL->value],
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, []);

        $this->assertEquals(5000, $pricing->subtotalCents);
        $this->assertEquals(0, $pricing->discountCents);
        $this->assertEquals(0, $pricing->shippingCents);
        $this->assertEquals(SubscriptionType::DIGITAL->value, $pricing->deliveryType);
        $this->assertNull($pricing->voucherId);
    }

    public function test_calculate_print_subscription_with_shipping(): void
    {
        $plan = $this->createMockPlan(50.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $resolvedPrice = ResolvedSubscriptionPrice::fromPlanPrice(
            planPrice: 50.00,
            currency: 'USD',
            variant: SubscriptionType::PRINTED->value,
            discountAmount: 0,
            voucherId: null
        );

        $this->pricingResolver->shouldReceive('resolve')->once()->andReturn($resolvedPrice);

        $this->shippingService->shouldReceive('calculateShipping')
            ->with(50.00, Mockery::any())
            ->andReturn(10.00);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => SubscriptionType::PRINTED->value],
        ];

        $checkoutData = [
            'address' => '123 Main St',
            'address2' => 'Apt 4',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, $checkoutData);

        $this->assertEquals(5000, $pricing->subtotalCents);
        $this->assertEquals(1000, $pricing->shippingCents);
        $this->assertEquals(SubscriptionType::PRINTED->value, $pricing->deliveryType);
        $this->assertNotNull($pricing->shippingAddressSnapshot);
        $this->assertEquals('123 Main St', $pricing->shippingAddressSnapshot['address_line_1']);
        $this->assertEquals('Apt 4', $pricing->shippingAddressSnapshot['address_line_2']);
        $this->assertEquals('New York', $pricing->shippingAddressSnapshot['city']);
    }

    public function test_calculate_print_subscription_without_address_returns_null_snapshot(): void
    {
        $plan = $this->createMockPlan(50.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $resolvedPrice = ResolvedSubscriptionPrice::fromPlanPrice(
            planPrice: 50.00,
            currency: 'USD',
            variant: SubscriptionType::PRINTED->value,
            discountAmount: 0,
            voucherId: null
        );

        $this->pricingResolver->shouldReceive('resolve')->once()->andReturn($resolvedPrice);

        $this->shippingService->shouldReceive('calculateShipping')
            ->with(50.00, Mockery::any())
            ->andReturn(10.00);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => SubscriptionType::PRINTED->value],
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, []);

        $this->assertEquals(5000, $pricing->subtotalCents);
        $this->assertEquals(1000, $pricing->shippingCents);
        $this->assertNull($pricing->shippingAddressSnapshot);
    }

    public function test_calculate_with_valid_voucher(): void
    {
        $plan = $this->createMockPlan(50.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 999;

        $resolvedPrice = ResolvedSubscriptionPrice::fromPlanPrice(
            planPrice: 50.00,
            currency: 'USD',
            variant: SubscriptionType::DIGITAL->value,
            discountAmount: 10.00,
            voucherId: 999
        );
        $resolvedPrice->voucher = $voucher;

        $this->pricingResolver->shouldReceive('resolve')
            ->once()
            ->with($plan, ['variant' => SubscriptionType::DIGITAL->value, 'pricing_tier_id' => null, 'voucher_code' => 'SAVE10'], 123)
            ->andReturn($resolvedPrice);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => SubscriptionType::DIGITAL->value],
        ];

        $pricing = $this->calculator->calculateForCartItem($item, 'SAVE10', $member, []);

        $this->assertEquals(4000, $pricing->subtotalCents);
        $this->assertEquals(1000, $pricing->discountCents);
        $this->assertEquals(999, $pricing->voucherId);
    }

    public function test_calculate_with_invalid_voucher_applies_no_discount(): void
    {
        $plan = $this->createMockPlan(50.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $this->pricingResolver->shouldReceive('resolve')
            ->once()
            ->with($plan, ['variant' => SubscriptionType::DIGITAL->value, 'pricing_tier_id' => null, 'voucher_code' => 'INVALID'], 123)
            ->andThrow(new \InvalidArgumentException('Voucher expired'));

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => SubscriptionType::DIGITAL->value],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Voucher expired');

        $this->calculator->calculateForCartItem($item, 'INVALID', $member, []);
    }

    public function test_calculate_uses_plan_price_not_cart_price(): void
    {
        $plan = $this->createMockPlan(60.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $resolvedPrice = ResolvedSubscriptionPrice::fromPlanPrice(
            planPrice: 60.00,
            currency: 'USD',
            variant: SubscriptionType::DIGITAL->value,
            discountAmount: 0,
            voucherId: null
        );

        $this->pricingResolver->shouldReceive('resolve')->once()->andReturn($resolvedPrice);

        $item = [
            'subscription_plan_id' => 1,
            'price' => 50.00, // stale cart price — must be ignored
            'options' => ['delivery_type' => SubscriptionType::DIGITAL->value],
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, []);

        $this->assertEquals(6000, $pricing->subtotalCents);
    }

    public function test_calculate_carries_resolved_pricing_tier_id(): void
    {
        $plan = $this->createMockPlan(60.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $resolvedPrice = ResolvedSubscriptionPrice::fromPricingTier(
            pricingTierId: 77,
            variant: SubscriptionType::DIGITAL->value,
            basePrice: 60.00,
            salePrice: 45.00,
            currency: 'USD',
            discountAmount: 0,
            voucherId: null
        );

        $this->pricingResolver->shouldReceive('resolve')
            ->once()
            ->with($plan, ['variant' => SubscriptionType::DIGITAL->value, 'pricing_tier_id' => 77, 'voucher_code' => null], 123)
            ->andReturn($resolvedPrice);

        $item = [
            'subscription_plan_id' => 1,
            'options' => [
                'delivery_type' => SubscriptionType::DIGITAL->value,
                'pricing_tier_id' => 77,
            ],
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, []);

        $this->assertSame(77, $pricing->pricingTierId);
        $this->assertSame(4500, $pricing->subtotalCents);
    }

    public function test_calculate_throws_on_invalid_plan(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->planRepository->shouldReceive('find')->with(999)->andReturn(null);

        $item = [
            'subscription_plan_id' => 999,
            'options' => ['delivery_type' => SubscriptionType::DIGITAL->value],
        ];

        $this->calculator->calculateForCartItem($item, null, $this->createMockMember(), []);
    }

    public function test_calculate_converts_amounts_to_cents_correctly(): void
    {
        $plan = $this->createMockPlan(49.99);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $resolvedPrice = ResolvedSubscriptionPrice::fromPlanPrice(
            planPrice: 49.99,
            currency: 'USD',
            variant: SubscriptionType::PRINTED->value,
            discountAmount: 0,
            voucherId: null
        );

        $this->pricingResolver->shouldReceive('resolve')->once()->andReturn($resolvedPrice);

        $this->shippingService->shouldReceive('calculateShipping')
            ->with(49.99, Mockery::any())
            ->andReturn(9.99);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => SubscriptionType::PRINTED->value],
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, ['address' => '123 Main St']);

        $this->assertEquals(4999, $pricing->subtotalCents);
        $this->assertEquals(999, $pricing->shippingCents);
        $this->assertEquals(5998, $pricing->totalCents);
    }

    // -----------------------------------------------------------------------
    // Bundle item tests
    // -----------------------------------------------------------------------

    public function test_bundle_item_uses_cart_price_directly_without_hitting_resolver(): void
    {
        // The resolver must NEVER be called for bundle items — the price is already set.
        $this->pricingResolver->shouldNotReceive('resolve');
        $this->planRepository->shouldNotReceive('find');

        $member = $this->createMockMember();

        $item = [
            'subscription_plan_id' => 1,
            'price' => 45.00,  // allocated price from SubscriptionBundlePriceAllocator
            'options' => [
                'type' => CartItemType::SUBSCRIPTION_BUNDLE->value,
                'delivery_type' => SubscriptionType::DIGITAL->value,
                'bundle_id' => 7,
            ],
        ];

        $pricing = $this->calculator->calculateForCartItem($item, 'SAVE10', $member, []);

        $this->assertEquals(4500, $pricing->subtotalCents);
        $this->assertEquals(0, $pricing->discountCents);  // no voucher on bundle
        $this->assertNull($pricing->voucherId);
        $this->assertEquals(SubscriptionType::DIGITAL->value, $pricing->deliveryType);
    }

    public function test_bundle_item_detected_via_bundle_id_option_without_type(): void
    {
        // Some code paths may set bundle_id but not explicitly set the type enum.
        $this->pricingResolver->shouldNotReceive('resolve');
        $this->planRepository->shouldNotReceive('find');

        $member = $this->createMockMember();

        $item = [
            'subscription_plan_id' => 2,
            'price' => 30.00,
            'options' => [
                'bundle_id' => 7,  // presence alone is sufficient
                'delivery_type' => SubscriptionType::DIGITAL->value,
            ],
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, []);

        $this->assertEquals(3000, $pricing->subtotalCents);
        $this->assertNull($pricing->voucherId);
    }

    public function test_bundle_item_print_delivery_still_calculates_shipping(): void
    {
        $this->pricingResolver->shouldNotReceive('resolve');

        $this->shippingService->shouldReceive('calculateShipping')
            ->with(45.00, Mockery::any())
            ->once()
            ->andReturn(5.00);

        $member = $this->createMockMember();

        $item = [
            'subscription_plan_id' => 1,
            'price' => 45.00,
            'options' => [
                'bundle_id' => 7,
                'delivery_type' => SubscriptionType::PRINTED->value,
            ],
        ];

        $checkoutData = [
            'address' => '10 Downing St',
            'city' => 'London',
            'country' => 'GB',
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, $checkoutData);

        $this->assertEquals(4500, $pricing->subtotalCents);
        $this->assertEquals(500, $pricing->shippingCents);
        $this->assertEquals(5000, $pricing->totalCents);
        $this->assertEquals(SubscriptionType::PRINTED->value, $pricing->deliveryType);
        $this->assertNotNull($pricing->shippingAddressSnapshot);
    }

    public function test_bundle_item_voucher_is_silently_ignored(): void
    {
        // A voucher code passed in must be dropped — no exception, no discount.
        $this->pricingResolver->shouldNotReceive('resolve');

        $member = $this->createMockMember();

        $item = [
            'subscription_plan_id' => 1,
            'price' => 20.00,
            'options' => [
                'bundle_id' => 3,
                'delivery_type' => SubscriptionType::DIGITAL->value,
            ],
        ];

        $pricing = $this->calculator->calculateForCartItem($item, 'SAVE50', $member, []);

        $this->assertEquals(2000, $pricing->subtotalCents);
        $this->assertEquals(0, $pricing->discountCents);
        $this->assertNull($pricing->voucherId);
    }

    public function test_bundle_item_digital_has_no_shipping_or_address_snapshot(): void
    {
        $this->shippingService->shouldNotReceive('calculateShipping');

        $member = $this->createMockMember();

        $item = [
            'subscription_plan_id' => 1,
            'price' => 25.00,
            'options' => [
                'bundle_id' => 5,
                'delivery_type' => SubscriptionType::DIGITAL->value,
            ],
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, []);

        $this->assertEquals(0, $pricing->shippingCents);
        $this->assertNull($pricing->shippingAddressSnapshot);
        $this->assertEquals(2500, $pricing->totalCents);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createMockPlan(float $price): SubscriptionPlan
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = $price;
        return $plan;
    }

    private function createMockMember(): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;
        return $member;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
