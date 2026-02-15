<?php

namespace App\Tests\Unit\Services\Vouchers;

use App\DTO\Vouchers\VoucherValidationContext;
use App\Models\Voucher;
use App\Repositories\Vouchers\VoucherRepository;
use App\Services\Vouchers\VoucherEligibilityResolver;
use App\Services\Vouchers\VoucherValidationService;
use Mockery;
use PHPUnit\Framework\TestCase;

class VoucherValidationServiceTest extends TestCase
{
    private $repository;
    private $eligibilityResolver;
    private VoucherValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(VoucherRepository::class);
        $this->eligibilityResolver = Mockery::mock(VoucherEligibilityResolver::class);

        $this->service = new VoucherValidationService(
            $this->repository,
            $this->eligibilityResolver
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testValidateValidVoucher()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->minimum_order_value = null;
        $voucher->per_user_limit = null;
        $voucher->campaign_id = null;
        $voucher->is_stackable = true;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->andReturn(true);
        $voucher->shouldReceive('calculateDiscount')->andReturn(10.00);

        $context = VoucherValidationContext::forProduct(1, 100.00, 123);

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertEquals(10.00, $result->discount);
    }

    public function testValidateInvalidVoucher()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->status = 'expired';
        $voucher->shouldReceive('isValid')->andReturn(false);

        $context = VoucherValidationContext::forProduct(1, 100.00);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertEquals('Voucher has expired', $result->message);
    }

    public function testValidateInactiveCampaign()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->campaign_id = 1;

        $campaign = Mockery::mock(\App\Models\Campaign::class)->makePartial();
        $campaign->status = 'inactive';

        $voucher->campaign = $campaign;
        $voucher->shouldReceive('isValid')->andReturn(true);

        $context = VoucherValidationContext::forProduct(1, 100.00);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertEquals('Campaign is not active', $result->message);
    }

    public function testValidateExceedsPerUserLimit()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->per_user_limit = 2;
        $voucher->campaign_id = null;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('getUserUsageCount')->with(123)->andReturn(2);

        $context = VoucherValidationContext::forProduct(1, 100.00, 123);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('already used', $result->message);
    }

    public function testValidateSubscriptionVoucherSuccess()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->per_user_limit = null;
        $voucher->campaign_id = null;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('appliesToSubscriptions')->andReturn(true);
        $voucher->shouldReceive('isApplicableToSubscriptionPlan')->with(1)->andReturn(true);
        $voucher->shouldReceive('calculateSubscriptionDiscount')->with(29.99)->andReturn(2.99);

        $context = VoucherValidationContext::forSubscription(1, 29.99, 123);

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertEquals(2.99, $result->discount);
        $this->assertEquals(27.00, $result->toArray()['final_price']);
    }

    public function testValidateSubscriptionVoucherNotApplicable()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('appliesToSubscriptions')->andReturn(false);

        $context = VoucherValidationContext::forSubscription(1, 29.99);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertEquals('This voucher cannot be used for subscriptions', $result->message);
    }

    public function testValidateSubscriptionVoucherWrongPlan()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('appliesToSubscriptions')->andReturn(true);
        $voucher->shouldReceive('isApplicableToSubscriptionPlan')->with(2)->andReturn(false);

        $context = VoucherValidationContext::forSubscription(2, 29.99);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertEquals('Voucher not applicable to this subscription plan', $result->message);
    }

    public function testValidateProductNotApplicable()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->with(1)->andReturn(false);

        $context = VoucherValidationContext::forProduct(1, 100.00);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertEquals('Voucher not applicable to this product', $result->message);
    }

    public function testValidateProductBelowMinimum()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->minimum_order_value = 50.00;
        $voucher->campaign_id = null;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->andReturn(true);

        $context = VoucherValidationContext::forProduct(1, 30.00);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Minimum order value', $result->message);
    }

    public function testValidateCartWithEligibleItems()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->minimum_order_value = null;
        $voucher->per_user_limit = null;
        $voucher->campaign_id = null;
        $voucher->is_stackable = true;

        $cartItems = [
            ['product_id' => 1, 'subtotal' => 50.00],
            ['product_id' => 2, 'subtotal' => 50.00]
        ];

        $eligibleItems = [
            ['product_id' => 1, 'subtotal' => 50.00]
        ];

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('calculateDiscount')->with(50.00)->andReturn(5.00);

        $this->eligibilityResolver->shouldReceive('resolveEligibleItems')
            ->once()
            ->with($voucher, $cartItems)
            ->andReturn($eligibleItems);

        $context = VoucherValidationContext::forCheckout($cartItems, 123);

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertEquals(5.00, $result->discount);
        $this->assertEquals(50.00, $result->eligibleSubtotal);
        $this->assertCount(1, $result->eligibleItems);
    }

    public function testValidateCartWithNoEligibleItems()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->campaign_id = null;

        $cartItems = [
            ['product_id' => 1, 'subtotal' => 50.00]
        ];

        $voucher->shouldReceive('isValid')->andReturn(true);

        $this->eligibilityResolver->shouldReceive('resolveEligibleItems')
            ->once()
            ->andReturn([]);

        $context = VoucherValidationContext::forCheckout($cartItems);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertEquals('Voucher is not applicable to any items in your cart', $result->message);
    }

    public function testValidateCartBelowMinimumEligibleSubtotal()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->minimum_order_value = 100.00;
        $voucher->campaign_id = null;

        $cartItems = [
            ['product_id' => 1, 'subtotal' => 50.00]
        ];

        $eligibleItems = [
            ['product_id' => 1, 'subtotal' => 50.00]
        ];

        $voucher->shouldReceive('isValid')->andReturn(true);

        $this->eligibilityResolver->shouldReceive('resolveEligibleItems')
            ->once()
            ->andReturn($eligibleItems);

        $context = VoucherValidationContext::forCheckout($cartItems);

        $result = $this->service->validate($voucher, $context);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Minimum order value', $result->message);
    }

    public function testValidateCartWithOfferDiscountNonStackable()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->minimum_order_value = null;
        $voucher->per_user_limit = null;
        $voucher->campaign_id = null;
        $voucher->is_stackable = false;

        $cartItems = [
            ['product_id' => 1, 'subtotal' => 50.00]
        ];

        $eligibleItems = [
            ['product_id' => 1, 'subtotal' => 50.00]
        ];

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('calculateDiscount')->andReturn(5.00);

        $this->eligibilityResolver->shouldReceive('resolveEligibleItems')
            ->once()
            ->with($voucher, $cartItems)
            ->andReturn($eligibleItems);

        $context = VoucherValidationContext::forCheckout(
            cartItems: $cartItems,
            userId: null,
            hasOfferDiscount: true
        );

        $result = $this->service->validate($voucher, $context);

        $this->assertTrue($result->valid);
        $this->assertTrue($result->requiresOverrideDecision);
        $this->assertFalse($result->isStackable);
    }
}