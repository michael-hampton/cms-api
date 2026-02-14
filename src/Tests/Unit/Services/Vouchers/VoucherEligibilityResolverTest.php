<?php

namespace App\Tests\Unit\Services\Vouchers;

use App\Models\Voucher;
use App\Services\Vouchers\VoucherEligibilityResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class VoucherEligibilityResolverTest extends TestCase
{
    private VoucherEligibilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new VoucherEligibilityResolver();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testResolveEligibleItemsWithProduct()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isApplicableToProduct')->with(1)->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->with(2)->andReturn(false);

        $cartItems = [
            ['product_id' => 1, 'subtotal' => 50.00],
            ['product_id' => 2, 'subtotal' => 30.00]
        ];

        $result = $this->resolver->resolveEligibleItems($voucher, $cartItems);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['product_id']);
    }

    public function testResolveEligibleItemsWithSubscription()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isApplicableToSubscriptionPlan')->with(1)->andReturn(true);

        $cartItems = [
            ['subscription_plan_id' => 1, 'subtotal' => 29.99]
        ];

        $result = $this->resolver->resolveEligibleItems($voucher, $cartItems);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['subscription_plan_id']);
    }

    public function testResolveEligibleItemsWithMixed()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isApplicableToProduct')->with(1)->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->with(2)->andReturn(false);
        $voucher->shouldReceive('isApplicableToSubscriptionPlan')->with(1)->andReturn(true);

        $cartItems = [
            ['product_id' => 1, 'subtotal' => 50.00],
            ['product_id' => 2, 'subtotal' => 30.00],
            ['subscription_plan_id' => 1, 'subtotal' => 29.99]
        ];

        $result = $this->resolver->resolveEligibleItems($voucher, $cartItems);

        $this->assertCount(2, $result);
    }

    public function testResolveEligibleItemsWithNone()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isApplicableToProduct')->andReturn(false);

        $cartItems = [
            ['product_id' => 1, 'subtotal' => 50.00]
        ];

        $result = $this->resolver->resolveEligibleItems($voucher, $cartItems);

        $this->assertEmpty($result);
    }

    public function testResolveEligibleItemsWithEmptyCart()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();

        $result = $this->resolver->resolveEligibleItems($voucher, []);

        $this->assertEmpty($result);
    }
}