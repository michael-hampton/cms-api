<?php

namespace App\Tests\Unit\Actions\Stock;

use App\Actions\Stock\PurchaseProductAction;
use App\Exceptions\Stock\StockException;
use App\Models\Product;
use App\Services\Stock\StockService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PurchaseProductActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private PurchaseProductAction $action;
    private StockService|MockInterface $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = Mockery::mock(StockService::class);
        $this->action = new PurchaseProductAction($this->stockService);
    }

    public function test_execute_delegates_to_stock_service_allocate(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        $this->stockService
            ->shouldReceive('allocate')
            ->once()
            ->with($product, 2, 5);

        $this->action->execute($product, 2);
    }

    public function test_execute_passes_custom_low_stock_threshold(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        $this->stockService
            ->shouldReceive('allocate')
            ->once()
            ->with($product, 3, 10);

        $this->action->execute($product, 3, 10);
    }

    public function test_execute_propagates_stock_exception(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Out of Stock Book';
        $product->stock_quantity = 0;

        $this->stockService
            ->shouldReceive('allocate')
            ->once()
            ->andThrow(StockException::insufficientStock('Out of Stock Book', 0, 1));

        $this->expectException(StockException::class);
        $this->expectExceptionMessage("Insufficient stock for 'Out of Stock Book'");

        $this->action->execute($product, 1);
    }

    public function test_execute_uses_default_threshold_of_five(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        $this->stockService
            ->shouldReceive('allocate')
            ->once()
            ->with($product, 1, 5); // default threshold must be 5

        $this->action->execute($product, 1);
    }
}