<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Vouchers\VoucherValidationResult;
use App\Models\Member;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shipping\ShippingService;
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

    public function test_calculate_digital_subscription_no_voucher(): void
    {
        $plan = $this->createMockPlan(50.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($plan);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => 'digital']
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, []);

        $this->assertEquals(5000, $pricing->subtotalCents);
        $this->assertEquals(0, $pricing->discountCents);
        $this->assertEquals(0, $pricing->shippingCents);
        $this->assertEquals('digital', $pricing->deliveryType);
        $this->assertNull($pricing->voucherId);
    }

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

    public function test_calculate_print_subscription_with_shipping(): void
    {
        $plan = $this->createMockPlan(50.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($plan);

        $this->shippingService->shouldReceive('calculateShipping')
            ->with(50.00, Mockery::any())
            ->andReturn(10.00);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => 'print']
        ];

        // FIXED: Pass address data in checkout data
        $checkoutData = [
            'address' => '123 Main St',
            'address2' => 'Apt 4',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US'
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, $checkoutData);

        $this->assertEquals(5000, $pricing->subtotalCents);
        $this->assertEquals(1000, $pricing->shippingCents);
        $this->assertEquals('print', $pricing->deliveryType);
        $this->assertNotNull($pricing->shippingAddressSnapshot);
        $this->assertEquals('123 Main St', $pricing->shippingAddressSnapshot['address_line_1']);
        $this->assertEquals('Apt 4', $pricing->shippingAddressSnapshot['address_line_2']);
        $this->assertEquals('New York', $pricing->shippingAddressSnapshot['city']);
    }

    public function test_calculate_print_subscription_without_address_returns_null_snapshot(): void
    {
        $plan = $this->createMockPlan(50.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($plan);

        $this->shippingService->shouldReceive('calculateShipping')
            ->with(50.00, Mockery::any())
            ->andReturn(10.00);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => 'print']
        ];

        // No address in checkout data
        $pricing = $this->calculator->calculateForCartItem($item, null, $member, []);

        $this->assertEquals(5000, $pricing->subtotalCents);
        $this->assertEquals(1000, $pricing->shippingCents);
        $this->assertNull($pricing->shippingAddressSnapshot);
    }

    public function test_calculate_with_valid_voucher(): void
    {
        $plan = $this->createMockPlan(50.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($plan);

        $voucherResult = new VoucherValidationResult(
            true,
            'SAVE10',
            10.00,
            999
        );

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->with('SAVE10', 1, 123)
            ->andReturn($voucherResult);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => 'digital']
        ];

        $pricing = $this->calculator->calculateForCartItem($item, 'SAVE10', $member, []);

        $this->assertEquals(5000, $pricing->subtotalCents);
        $this->assertEquals(1000, $pricing->discountCents);
        $this->assertEquals(999, $pricing->voucherId);
    }

    public function test_calculate_with_invalid_voucher_applies_no_discount(): void
    {
        $plan = $this->createMockPlan(50.00);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($plan);

        $voucherResult = new VoucherValidationResult(
            false,
            null,
            0,
            null
        );

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->with('INVALID', 1, 123)
            ->andReturn($voucherResult);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => 'digital']
        ];

        $pricing = $this->calculator->calculateForCartItem($item, 'INVALID', $member, []);

        $this->assertEquals(5000, $pricing->subtotalCents);
        $this->assertEquals(0, $pricing->discountCents);
        $this->assertNull($pricing->voucherId);
    }

    public function test_calculate_uses_plan_price_not_cart_price(): void
    {
        $plan = $this->createMockPlan(60.00); // Authoritative price
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($plan);

        $item = [
            'subscription_plan_id' => 1,
            'price' => 50.00, // Stale cart price (should be ignored)
            'options' => ['delivery_type' => 'digital']
        ];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, []);

        // Should use plan price (60.00), not cart price (50.00)
        $this->assertEquals(6000, $pricing->subtotalCents);
    }

    public function test_calculate_throws_on_invalid_plan(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->planRepository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $item = [
            'subscription_plan_id' => 999,
            'options' => ['delivery_type' => 'digital']
        ];

        $this->calculator->calculateForCartItem($item, null, $this->createMockMember(), []);
    }

    public function test_calculate_converts_amounts_to_cents_correctly(): void
    {
        $plan = $this->createMockPlan(49.99);
        $member = $this->createMockMember();

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($plan);

        $this->shippingService->shouldReceive('calculateShipping')
            ->with(49.99, Mockery::any())
            ->andReturn(9.99);

        $item = [
            'subscription_plan_id' => 1,
            'options' => ['delivery_type' => 'print']
        ];

        $checkoutData = ['address' => '123 Main St'];

        $pricing = $this->calculator->calculateForCartItem($item, null, $member, $checkoutData);

        // Verify proper rounding to cents
        $this->assertEquals(4999, $pricing->subtotalCents); // 49.99 * 100
        $this->assertEquals(999, $pricing->shippingCents);   // 9.99 * 100
        $this->assertEquals(5998, $pricing->totalCents);     // 4999 + 999
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->voucherService = Mockery::mock(VoucherService::class);
        $this->shippingService = Mockery::mock(ShippingService::class);

        $this->calculator = new SubscriptionPricingService(
            $this->planRepository,
            $this->voucherService,
            $this->shippingService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}