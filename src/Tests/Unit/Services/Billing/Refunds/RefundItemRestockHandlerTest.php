<?php

namespace App\Tests\Unit\Services\Billing\Refunds;

use App\Framework\Support\Collection;
use App\Models\Product;
use App\Models\RefundItem;
use App\Repositories\Billing\RefundRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Billing\Refund\RefundItemRestockHandler;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class RefundItemRestockHandlerTest extends TestCase
{
    private RefundRepository $refundRepository;
    private ProductRepository $productRepository;
    private RefundItemRestockHandler $handler;

    public function testRestocksItemsSuccessfully(): void
    {
        $refundId = 1;

        $item1 = m::mock(RefundItem::class)->makePartial();
        $item1->product_id = 1;
        $item1->refund_quantity = 2;

        $item2 = m::mock(RefundItem::class)->makePartial();
        $item2->product_id = 2;
        $item2->refund_quantity = 3;

        $items = new Collection([$item1, $item2]);

        $this->refundRepository
            ->shouldReceive('getRefundItems')
            ->once()
            ->with($refundId)
            ->andReturn($items);

        $product1 = m::mock(Product::class)->makePartial();
        $product1->id = 1;
        $product1->stock_quantity = 10;

        $product2 = m::mock(Product::class)->makePartial();
        $product2->id = 2;
        $product2->stock_quantity = 5;

        $this->productRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product1);

        $this->productRepository
            ->shouldReceive('find')
            ->once()
            ->with(2)
            ->andReturn($product2);

        $this->productRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 12]);

        $this->productRepository
            ->shouldReceive('update')
            ->once()
            ->with(2, ['stock_quantity' => 8]);

        $this->handler->restockItems($refundId);
        $this->assertTrue(true);
    }

    public function testSkipsItemsWithNoProductId(): void
    {
        $refundId = 1;

        $item1 = m::mock(RefundItem::class)->makePartial();
        $item1->product_id = null;
        $item1->refund_quantity = 2;

        $item2 = m::mock(RefundItem::class)->makePartial();
        $item2->product_id = 1;
        $item2->refund_quantity = 3;

        $items = new Collection([$item1, $item2]);

        $this->refundRepository
            ->shouldReceive('getRefundItems')
            ->once()
            ->with($refundId)
            ->andReturn($items);

        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        $this->productRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->productRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 13]);

        // Should not attempt to find or update product for item1
        $this->handler->restockItems($refundId);
        $this->assertTrue(true);
    }

    public function testSkipsItemsWithZeroRefundQuantity(): void
    {
        $refundId = 1;

        $item1 = m::mock(RefundItem::class)->makePartial();
        $item1->product_id = 1;
        $item1->refund_quantity = 0;

        $item2 = m::mock(RefundItem::class)->makePartial();
        $item2->product_id = 2;
        $item2->refund_quantity = 5;

        $items = new Collection([$item1, $item2]);

        $this->refundRepository
            ->shouldReceive('getRefundItems')
            ->once()
            ->with($refundId)
            ->andReturn($items);

        $product = m::mock(Product::class)->makePartial();
        $product->id = 2;
        $product->stock_quantity = 10;

        $this->productRepository
            ->shouldReceive('find')
            ->once()
            ->with(2)
            ->andReturn($product);

        $this->productRepository
            ->shouldReceive('update')
            ->once()
            ->with(2, ['stock_quantity' => 15]);

        $this->handler->restockItems($refundId);
        $this->assertTrue(true);
    }

    public function testSkipsItemsWithNegativeRefundQuantity(): void
    {
        $refundId = 1;

        $item = m::mock(RefundItem::class)->makePartial();
        $item->product_id = 1;
        $item->refund_quantity = -5;

        $items = new Collection([$item]);

        $this->refundRepository
            ->shouldReceive('getRefundItems')
            ->once()
            ->with($refundId)
            ->andReturn($items);

        // Should not attempt to find or update product
        $this->handler->restockItems($refundId);
        $this->assertTrue(true);
    }

    public function testSkipsWhenProductNotFound(): void
    {
        $refundId = 1;

        $item = m::mock(RefundItem::class)->makePartial();
        $item->product_id = 999;
        $item->refund_quantity = 2;

        $items = new Collection([$item]);

        $this->refundRepository
            ->shouldReceive('getRefundItems')
            ->once()
            ->with($refundId)
            ->andReturn($items);

        $this->productRepository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        // Should not attempt to update since product doesn't exist
        $this->handler->restockItems($refundId);
        $this->assertTrue(true);
    }

    public function testHandlesEmptyRefundItems(): void
    {
        $refundId = 1;

        $items = new Collection([]);

        $this->refundRepository
            ->shouldReceive('getRefundItems')
            ->once()
            ->with($refundId)
            ->andReturn($items);

        // Should not attempt any product operations
        $this->handler->restockItems($refundId);
        $this->assertTrue(true);
    }

    public function testRestocksMultipleItemsOfSameProduct(): void
    {
        $refundId = 1;

        // Two refund items for the same product
        $item1 = m::mock(RefundItem::class)->makePartial();
        $item1->product_id = 1;
        $item1->refund_quantity = 2;

        $item2 = m::mock(RefundItem::class)->makePartial();
        $item2->product_id = 1;
        $item2->refund_quantity = 3;

        $items = new Collection([$item1, $item2]);

        $this->refundRepository
            ->shouldReceive('getRefundItems')
            ->once()
            ->with($refundId)
            ->andReturn($items);

        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        $this->productRepository
            ->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($product);

        // First update: 10 + 2 = 12
        $this->productRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 12]);

        // Second update: 10 + 3 = 13 (note: uses original stock, not updated)
        $this->productRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 13]);

        $this->handler->restockItems($refundId);
        $this->assertTrue(true);
    }

    public function testHandlesMixedValidAndInvalidItems(): void
    {
        $refundId = 1;

        $validItem = m::mock(RefundItem::class)->makePartial();
        $validItem->product_id = 1;
        $validItem->refund_quantity = 5;

        $noProductIdItem = m::mock(RefundItem::class)->makePartial();
        $noProductIdItem->product_id = null;
        $noProductIdItem->refund_quantity = 2;

        $zeroQuantityItem = m::mock(RefundItem::class)->makePartial();
        $zeroQuantityItem->product_id = 2;
        $zeroQuantityItem->refund_quantity = 0;

        $nonExistentProductItem = m::mock(RefundItem::class)->makePartial();
        $nonExistentProductItem->product_id = 999;
        $nonExistentProductItem->refund_quantity = 3;

        $items = new Collection([
            $validItem,
            $noProductIdItem,
            $zeroQuantityItem,
            $nonExistentProductItem
        ]);

        $this->refundRepository
            ->shouldReceive('getRefundItems')
            ->once()
            ->with($refundId)
            ->andReturn($items);

        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        $this->productRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->productRepository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->productRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 15]);

        $this->handler->restockItems($refundId);
        $this->assertTrue(true);
    }

    public function testHandlesLargeQuantityRestock(): void
    {
        $refundId = 1;

        $item = m::mock(RefundItem::class)->makePartial();
        $item->product_id = 1;
        $item->refund_quantity = 1000;

        $items = new Collection([$item]);

        $this->refundRepository
            ->shouldReceive('getRefundItems')
            ->once()
            ->with($refundId)
            ->andReturn($items);

        $product = m::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 50;

        $this->productRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->productRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 1050]);

        $this->handler->restockItems($refundId);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundRepository = m::mock(RefundRepository::class);
        $this->productRepository = m::mock(ProductRepository::class);

        $this->handler = new RefundItemRestockHandler(
            $this->refundRepository,
            $this->productRepository
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}