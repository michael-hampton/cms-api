<?php

namespace App\Tests\Unit\Services\Billing\Preorder;

use App\Models\Product;
use App\Repositories\Billing\OrderItemRepository;
use App\Services\Billing\Preorder\Actions\CalculateSellableStockAction;
use Mockery;
use PHPUnit\Framework\TestCase;

class CalculateSellableStockActionTest extends TestCase
{
    private OrderItemRepository $orderItemRepository;
    private CalculateSellableStockAction $action;

    public function test_returns_full_stock_when_no_pending_preorders(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 100;

        $this->orderItemRepository->shouldReceive('getPendingPreorderQuantity')
            ->once()
            ->with(1)
            ->andReturn(0);

        $sellableStock = $this->action->execute($product);

        $this->assertEquals(100, $sellableStock);
    }

    public function test_subtracts_reserved_quantity_from_stock(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 50;

        // 30 units reserved for pending preorders
        $this->orderItemRepository->shouldReceive('getPendingPreorderQuantity')
            ->once()
            ->with(1)
            ->andReturn(30);

        $sellableStock = $this->action->execute($product);

        $this->assertEquals(20, $sellableStock); // 50 - 30
    }

    public function test_returns_zero_when_reserved_exceeds_stock(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        // More reservations than stock (shouldn't happen, but defensive)
        $this->orderItemRepository->shouldReceive('getPendingPreorderQuantity')
            ->once()
            ->with(1)
            ->andReturn(15);

        $sellableStock = $this->action->execute($product);

        $this->assertEquals(0, $sellableStock); // max(0, 10 - 15)
    }

    public function test_returns_zero_when_stock_is_zero(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 0;

        $this->orderItemRepository->shouldReceive('getPendingPreorderQuantity')
            ->once()
            ->with(1)
            ->andReturn(0);

        $sellableStock = $this->action->execute($product);

        $this->assertEquals(0, $sellableStock);
    }

    public function test_handles_partial_allocations_correctly(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 100;

        // Example:
        // Order 1: quantity=10, allocated=5 -> reserves 5
        // Order 2: quantity=20, allocated=0 -> reserves 20
        // Total reserved: 25
        $this->orderItemRepository->shouldReceive('getPendingPreorderQuantity')
            ->once()
            ->with(1)
            ->andReturn(25);

        $sellableStock = $this->action->execute($product);

        $this->assertEquals(75, $sellableStock); // 100 - 25
    }

    public function test_sellable_stock_calculation_example_scenario(): void
    {
        // Real-world scenario:
        // Product has 50 units in stock
        // Preorder A: ordered 30, allocated 10 -> reserves 20
        // Preorder B: ordered 15, allocated 0 -> reserves 15
        // Total reserved: 35
        // Sellable: 50 - 35 = 15

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 50;

        $this->orderItemRepository->shouldReceive('getPendingPreorderQuantity')
            ->once()
            ->with(1)
            ->andReturn(35);

        $sellableStock = $this->action->execute($product);

        $this->assertEquals(15, $sellableStock);
    }

    public function test_prevents_overselling_when_preorders_pending(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        // 10 units reserved for preorders
        $this->orderItemRepository->shouldReceive('getPendingPreorderQuantity')
            ->once()
            ->with(1)
            ->andReturn(10);

        $sellableStock = $this->action->execute($product);

        // No stock available for new purchases
        $this->assertEquals(0, $sellableStock);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderItemRepository = Mockery::mock(OrderItemRepository::class);
        $this->action = new CalculateSellableStockAction($this->orderItemRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}