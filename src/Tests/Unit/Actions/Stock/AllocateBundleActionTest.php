<?php

namespace App\Tests\Unit\Actions\Stock;

use App\Actions\Stock\AllocateBundleAction;
use App\Actions\Stock\PurchaseProductAction;
use App\Exceptions\Stock\StockException;
use App\Models\Product;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class AllocateBundleActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private AllocateBundleAction $action;
    private PurchaseProductAction|MockInterface $purchaseProductAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purchaseProductAction = Mockery::mock(PurchaseProductAction::class);
        $this->action = new AllocateBundleAction($this->purchaseProductAction);
    }

    public function test_execute_calls_purchase_product_action_for_each_product(): void
    {
        $productA = Mockery::mock(Product::class)->makePartial();
        $productA->id = 1;

        $productB = Mockery::mock(Product::class)->makePartial();
        $productB->id = 2;

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($productA, 1, 5);

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($productB, 1, 5);

        $this->action->execute([$productA, $productB]);
    }

    public function test_execute_passes_quantity_to_each_product(): void
    {
        $productA = Mockery::mock(Product::class)->makePartial();
        $productA->id = 1;

        $productB = Mockery::mock(Product::class)->makePartial();
        $productB->id = 2;

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($productA, 3, 5);

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($productB, 3, 5);

        $this->action->execute([$productA, $productB], 3);
    }

    public function test_execute_with_empty_product_list_does_nothing(): void
    {
        $this->purchaseProductAction->shouldNotReceive('execute');

        $this->action->execute([]);
    }

    public function test_execute_with_single_product_calls_action_once(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($product, 1, 5);

        $this->action->execute([$product]);
    }

    public function test_execute_propagates_stock_exception_on_first_failure(): void
    {
        $productA = Mockery::mock(Product::class)->makePartial();
        $productA->id = 1;
        $productA->name = 'Book A';

        $productB = Mockery::mock(Product::class)->makePartial();
        $productB->id = 2;

        // First product fails — second must never be called
        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($productA, 1, 5)
            ->andThrow(StockException::insufficientStock('Book A', 0, 1));

        $this->purchaseProductAction->shouldNotReceive('execute')
            ->with($productB, Mockery::any(), Mockery::any());

        $this->expectException(StockException::class);
        $this->expectExceptionMessage("Insufficient stock for 'Book A'");

        $this->action->execute([$productA, $productB]);
    }

    public function test_execute_propagates_stock_exception_on_middle_failure(): void
    {
        $productA = Mockery::mock(Product::class)->makePartial();
        $productA->id = 1;

        $productB = Mockery::mock(Product::class)->makePartial();
        $productB->id = 2;
        $productB->name = 'Middle Book';

        $productC = Mockery::mock(Product::class)->makePartial();
        $productC->id = 3;

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($productA, 1, 5);

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($productB, 1, 5)
            ->andThrow(StockException::insufficientStock('Middle Book', 1, 2));

        $this->purchaseProductAction
            ->shouldNotReceive('execute')
            ->with($productC, Mockery::any(), Mockery::any());

        $this->expectException(StockException::class);

        $this->action->execute([$productA, $productB, $productC]);
    }

    public function test_execute_passes_custom_low_stock_threshold_to_each_product(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($product, 2, 10);

        $this->action->execute([$product], 2, 10);
    }

    public function test_execute_accepts_a_collection_as_iterable(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        // Simulate a framework Collection (which implements IteratorAggregate)
        $collection = new \ArrayObject([$product]);

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($product, 1, 5);

        $this->action->execute($collection);
    }
}