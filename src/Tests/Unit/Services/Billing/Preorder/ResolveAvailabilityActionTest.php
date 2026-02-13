<?php

namespace App\Tests\Unit\Services\Billing\Preorder;

use App\Enums\Orders\OrderLineStatus;
use App\Models\Product;
use App\Services\Billing\Preorder\Actions\ResolveAvailabilityAction;
use App\Services\Billing\Preorder\Contracts\AvailabilityPolicyInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

class ResolveAvailabilityActionTest extends TestCase
{
    private ResolveAvailabilityAction $action;

    public function test_returns_ready_to_ship_when_stock_available(): void
    {
        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(true);
        $policy->shouldReceive('isPreOrder')->andReturn(false);

        $product = Mockery::mock(Product::class)->makePartial();
        $product->stock_quantity = 10;
        $product->shouldReceive('availabilityPolicy')->andReturn($policy);

        $result = $this->action->execute($product, 5);

        $this->assertEquals(OrderLineStatus::READY_TO_SHIP->value, $result['status']);
        $this->assertNull($result['expected_ship_date']);
        $this->assertFalse($result['is_preorder']);
    }

    public function test_returns_pending_preorder_when_no_stock_but_preorder_enabled(): void
    {
        $expectedDate = new \DateTime('+14 days');

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(true);
        $policy->shouldReceive('isPreOrder')->andReturn(true);
        $policy->shouldReceive('getExpectedShipDate')->andReturn($expectedDate);

        $product = Mockery::mock(Product::class)->makePartial();
        $product->stock_quantity = 0;
        $product->shouldReceive('availabilityPolicy')->andReturn($policy);

        $result = $this->action->execute($product, 3);

        $this->assertEquals(OrderLineStatus::PENDING_PREORDER->value, $result['status']);
        $this->assertEquals($expectedDate, $result['expected_ship_date']);
        $this->assertTrue($result['is_preorder']);
    }

    public function test_throws_exception_when_product_not_purchasable(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Product not available for purchase');

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(false);

        $product = Mockery::mock(Product::class)->makePartial();
        $product->shouldReceive('availabilityPolicy')->andReturn($policy);

        $this->action->execute($product, 5);
    }

    public function test_throws_exception_for_invalid_availability_state(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid availability state');

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(true);
        $policy->shouldReceive('isPreOrder')->andReturn(false);

        $product = Mockery::mock(Product::class)->makePartial();
        $product->stock_quantity = 0; // No stock
        $product->shouldReceive('availabilityPolicy')->andReturn($policy);

        // This is invalid: canPurchase=true but stock=0 and isPreOrder=false
        $this->action->execute($product, 5);
    }

    public function test_handles_exact_stock_quantity_match(): void
    {
        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(true);
        $policy->shouldReceive('isPreOrder')->andReturn(false);

        $product = Mockery::mock(Product::class)->makePartial();
        $product->stock_quantity = 5;
        $product->shouldReceive('availabilityPolicy')->andReturn($policy);

        $result = $this->action->execute($product, 5);

        $this->assertEquals(OrderLineStatus::READY_TO_SHIP->value, $result['status']);
        $this->assertFalse($result['is_preorder']);
    }

    public function test_returns_preorder_when_requested_quantity_exceeds_stock(): void
    {
        $expectedDate = new \DateTime('+7 days');

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(true);
        $policy->shouldReceive('isPreOrder')->andReturn(true);
        $policy->shouldReceive('getExpectedShipDate')->andReturn($expectedDate);

        $product = Mockery::mock(Product::class)->makePartial();
        $product->stock_quantity = 3;
        $product->shouldReceive('availabilityPolicy')->andReturn($policy);

        // Requesting 10 but only 3 in stock
        $result = $this->action->execute($product, 10);

        $this->assertEquals(OrderLineStatus::PENDING_PREORDER->value, $result['status']);
        $this->assertTrue($result['is_preorder']);
    }

    public function test_preorder_with_null_expected_ship_date_throws_exception(): void
    {
        $this->expectException(\Exception::class);

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(true);
        $policy->shouldReceive('isPreOrder')->andReturn(true);
        $policy->shouldReceive('getExpectedShipDate')->andReturn(null); // Invalid

        $product = Mockery::mock(Product::class)->makePartial();
        $product->stock_quantity = 0;
        $product->shouldReceive('availabilityPolicy')->andReturn($policy);

        $this->action->execute($product, 5);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ResolveAvailabilityAction();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}