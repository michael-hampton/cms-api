<?php

namespace App\Tests\Unit\Services\Billing\Preorder;

use App\Enums\Orders\OrderLineStatus;
use App\Framework\Database\Database;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductVariantRepository;
use App\Services\Billing\Preorder\Actions\AllocatePreorderStockAction;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class AllocatePreorderStockActionTest extends FunctionalTestCase
{
    private ProductRepository $productRepository;
    private OrderItemRepository $orderItemRepository;
    private Database $databaseMock;
    private AllocatePreorderStockAction $action;
    private ProductVariantRepository $productVariantRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->productVariantRepository = Mockery::mock(ProductVariantRepository::class);

        $this->action = new AllocatePreorderStockAction(
            $this->productRepository,
            $this->productVariantRepository,
            $this->orderItemRepository,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_allocates_stock_to_oldest_preorder_first(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 5;

        // Old preorder (created first)
        $oldOrder = Mockery::mock(OrderItem::class)->makePartial();
        $oldOrder->id = 1;
        $oldOrder->quantity = 3;
        $oldOrder->quantity_allocated = 0;
        $oldOrder->created_at = new \DateTime('-2 days');

        // New preorder (created later)
        $newOrder = Mockery::mock(OrderItem::class)->makePartial();
        $newOrder->id = 2;
        $newOrder->quantity = 4;
        $newOrder->quantity_allocated = 0;
        $newOrder->created_at = new \DateTime('-1 day');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productRepository->shouldReceive('lockForUpdate')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->orderItemRepository->shouldReceive('getPendingPreorders')
            ->once()
            ->with(1)
            ->andReturn(collect([$oldOrder, $newOrder]));

        // Old order gets fully allocated
        $this->orderItemRepository->shouldReceive('update')
            ->once()
            ->with(1, [
                'quantity_allocated' => 3,
                'status' => OrderLineStatus::READY_TO_SHIP->value,
            ]);

        // New order gets partial allocation
        $this->orderItemRepository->shouldReceive('update')
            ->once()
            ->with(2, [
                'quantity_allocated' => 2,
                'status' => OrderLineStatus::PENDING_PREORDER->value,
            ]);

        $this->productRepository->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 0]);

        $allocated = $this->action->execute($product);

        $this->assertEquals(5, $allocated);
    }

    public function test_returns_zero_when_no_stock_available(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 0;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productRepository->shouldReceive('lockForUpdate')
            ->once()
            ->with(1)
            ->andReturn($product);

        $allocated = $this->action->execute($product);

        $this->assertEquals(0, $allocated);
    }

    public function test_handles_partial_allocation_correctly(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 5;

        $orderLine = Mockery::mock(OrderItem::class)->makePartial();
        $orderLine->id = 1;
        $orderLine->quantity = 10;
        $orderLine->quantity_allocated = 0;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productRepository->shouldReceive('lockForUpdate')
            ->once()
            ->andReturn($product);

        $this->orderItemRepository->shouldReceive('getPendingPreorders')
            ->once()
            ->andReturn(collect([$orderLine]));

        // Partial allocation: 5 out of 10
        $this->orderItemRepository->shouldReceive('update')
            ->once()
            ->with(1, [
                'quantity_allocated' => 5,
                'status' => OrderLineStatus::PENDING_PREORDER->value,
            ]);

        $this->productRepository->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 0]);

        $allocated = $this->action->execute($product);

        $this->assertEquals(5, $allocated);
    }

    public function test_idempotency_already_allocated_items_skipped(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        // Already fully allocated
        $fullyAllocated = Mockery::mock(OrderItem::class)->makePartial();
        $fullyAllocated->id = 1;
        $fullyAllocated->quantity = 5;
        $fullyAllocated->quantity_allocated = 5;

        // Pending allocation
        $pending = Mockery::mock(OrderItem::class)->makePartial();
        $pending->id = 2;
        $pending->quantity = 8;
        $pending->quantity_allocated = 0;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productRepository->shouldReceive('lockForUpdate')
            ->once()
            ->andReturn($product);

        // getPendingPreorders filters out fully allocated
        $this->orderItemRepository->shouldReceive('getPendingPreorders')
            ->once()
            ->andReturn(collect([$pending])); // Only pending returned

        $this->orderItemRepository->shouldReceive('update')
            ->once()
            ->with(2, [
                'quantity_allocated' => 8,
                'status' => OrderLineStatus::READY_TO_SHIP->value,
            ]);

        $this->productRepository->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 2]);

        $allocated = $this->action->execute($product);

        $this->assertEquals(8, $allocated);
    }

    public function test_continues_partial_allocation_from_previous_run(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 5;

        // Previously partially allocated
        $partiallyAllocated = Mockery::mock(OrderItem::class)->makePartial();
        $partiallyAllocated->id = 1;
        $partiallyAllocated->quantity = 10;
        $partiallyAllocated->quantity_allocated = 3; // Already has 3

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productRepository->shouldReceive('lockForUpdate')
            ->once()
            ->andReturn($product);

        $this->orderItemRepository->shouldReceive('getPendingPreorders')
            ->once()
            ->andReturn(collect([$partiallyAllocated]));

        // Should allocate 5 more (3 + 5 = 8 total)
        $this->orderItemRepository->shouldReceive('update')
            ->once()
            ->with(1, [
                'quantity_allocated' => 8,
                'status' => OrderLineStatus::PENDING_PREORDER->value,
            ]);

        $this->productRepository->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 0]);

        $allocated = $this->action->execute($product);

        $this->assertEquals(5, $allocated);
    }

    public function test_stops_when_stock_exhausted(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 3;

        $order1 = Mockery::mock(OrderItem::class)->makePartial();
        $order1->id = 1;
        $order1->quantity = 2;
        $order1->quantity_allocated = 0;
        $order1->created_at = new \DateTime('-3 days');

        $order2 = Mockery::mock(OrderItem::class)->makePartial();
        $order2->id = 2;
        $order2->quantity = 5;
        $order2->quantity_allocated = 0;
        $order2->created_at = new \DateTime('-2 days');

        $order3 = Mockery::mock(OrderItem::class)->makePartial();
        $order3->id = 3;
        $order3->quantity = 3;
        $order3->quantity_allocated = 0;
        $order3->created_at = new \DateTime('-1 day');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productRepository->shouldReceive('lockForUpdate')
            ->once()
            ->andReturn($product);

        $this->orderItemRepository->shouldReceive('getPendingPreorders')
            ->once()
            ->andReturn(collect([$order1, $order2, $order3]));

        // Order 1: fully allocated (2 units)
        $this->orderItemRepository->shouldReceive('update')
            ->once()
            ->with(1, [
                'quantity_allocated' => 2,
                'status' => OrderLineStatus::READY_TO_SHIP->value,
            ]);

        // Order 2: partially allocated (1 unit)
        $this->orderItemRepository->shouldReceive('update')
            ->once()
            ->with(2, [
                'quantity_allocated' => 1,
                'status' => OrderLineStatus::PENDING_PREORDER->value,
            ]);

        // Order 3: NOT touched (no stock left)
        $this->orderItemRepository->shouldNotReceive('update')
            ->with(3, Mockery::any());

        $this->productRepository->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 0]);

        $allocated = $this->action->execute($product);

        $this->assertEquals(3, $allocated);
    }

    public function test_marks_line_ready_to_ship_when_fully_allocated(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        $orderLine = Mockery::mock(OrderItem::class)->makePartial();
        $orderLine->id = 1;
        $orderLine->quantity = 5;
        $orderLine->quantity_allocated = 0;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productRepository->shouldReceive('lockForUpdate')
            ->once()
            ->andReturn($product);

        $this->orderItemRepository->shouldReceive('getPendingPreorders')
            ->once()
            ->andReturn(collect([$orderLine]));

        // Should be marked as READY_TO_SHIP when quantity_allocated === quantity
        $this->orderItemRepository->shouldReceive('update')
            ->once()
            ->with(1, [
                'quantity_allocated' => 5,
                'status' => OrderLineStatus::READY_TO_SHIP->value,
            ]);

        $this->productRepository->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 5]);

        $result = $this->action->execute($product);
        $this->assertEquals(5, $result);
    }
}