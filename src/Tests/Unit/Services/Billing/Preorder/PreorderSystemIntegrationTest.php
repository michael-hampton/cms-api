<?php

namespace App\Tests\Unit\Services\Billing\Preorder;

use App\Enums\Orders\OrderLineStatus;
use App\Events\Orders\ProductStockUpdated;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Billing\Preorder\Actions\AllocatePreorderStockAction;
use App\Services\Billing\Preorder\Actions\CalculateSellableStockAction;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PreorderSystemIntegrationTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_complete_preorder_flow(): void
    {
        // 1. Create product with preorder enabled
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock_quantity' => 0,
            'preorder_enabled' => true,
            'preorder_restock_date' => now_datetime()->addDays(7),
            'site_id' => 1,
            'is_active' => true,
        ]);

        $order = $this->createOrder();

        // 2. Customer places preorder
        $orderLine1 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 99.99,
            'status' => OrderLineStatus::PENDING_PREORDER->value,
            'expected_ship_date' => $product->preorder_restock_date,
            'quantity_allocated' => 0,
            'product_name' => $product->name,
            'subtotal' => 99.99,
            'total' => 99.99,
        ]);

        $orderLine2 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 99.99,
            'status' => OrderLineStatus::PENDING_PREORDER->value,
            'expected_ship_date' => $product->preorder_restock_date,
            'quantity_allocated' => 0,
            'product_name' => $product->name,
            'subtotal' => 99.99,
            'total' => 99.99,
        ]);

        // 3. Stock arrives (6 units)
        $product->update(['stock_quantity' => 6]);
        $product = $product->fresh();

        event(new ProductStockUpdated($product, 0, 6));

        // 4. Allocation job runs
        $allocateAction = app(AllocatePreorderStockAction::class);
        $allocated = $allocateAction->execute($product);

        $this->assertEquals(6, $allocated);

        // 5. Verify allocation results
        $orderLine1 = $orderLine1->fresh();
        $orderLine2 = $orderLine2->fresh();
        $product = $product->fresh();

        // First order: fully allocated
        $this->assertEquals(5, $orderLine1->quantity_allocated);
        $this->assertEquals(OrderLineStatus::READY_TO_SHIP->value, $orderLine1->status);

        // Second order: partially allocated
        $this->assertEquals(1, $orderLine2->quantity_allocated);
        $this->assertEquals(OrderLineStatus::PENDING_PREORDER->value, $orderLine2->status);

        // Product: stock depleted
        $this->assertEquals(0, $product->stock_quantity);

        // 6. More stock arrives
        $product->update(['stock_quantity' => 5]);

        $allocated = $allocateAction->execute($product->fresh());

        $this->assertEquals(2, $allocated); // Only 2 more needed

        // 7. Second order now fully allocated
        $orderLine2 = $orderLine2->fresh();
        $this->assertEquals(3, $orderLine2->quantity_allocated);
        $this->assertEquals(OrderLineStatus::READY_TO_SHIP->value, $orderLine2->status);

        // 8. Remaining stock available
        $product = $product->fresh();
        $this->assertEquals(3, $product->stock_quantity);
    }

    public function test_prevents_starvation_of_preorders(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 49.99,
            'stock_quantity' => 0,
            'preorder_enabled' => true,
            'preorder_restock_date' => now_datetime()->addDays(14),
            'site_id' => 1,
            'is_active' => true,
        ]);

        $order = $this->createOrder();

        // Existing preorder
        $preorder = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 49.99,
            'status' => OrderLineStatus::PENDING_PREORDER->value,
            'expected_ship_date' => $product->preorder_restock_date,
            'quantity_allocated' => 0,
            'product_name' => $product->name,
            'subtotal' => 49.99,
            'total' => 49.99,
        ]);

        // Stock arrives: 8 units
        $product->update(['stock_quantity' => 8]);
        $product = $product->fresh();

        // Calculate sellable stock
        $sellableStockAction = app(CalculateSellableStockAction::class);
        $sellableStock = $sellableStockAction->execute($product);

        // Should be 0 - all stock reserved for preorder
        $this->assertEquals(0, $sellableStock);

        // Normal purchase should be blocked
        $policy = $product->availabilityPolicy();

        // Product should only be available as preorder
        $this->assertTrue($policy->canPurchase());
        $this->assertTrue($policy->isPreOrder());
    }
}