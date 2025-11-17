<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RefundItemModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $testUser;
    private Order $testOrder;
    private Refund $testRefund;

    private OrderItem $orderItem;

    public function testRefundRelationshipLoads(): void
    {
        $product = $this->createProduct();

        $refundItem = RefundItem::create([
            'order_item_id' => $this->orderItem->id,
            'refund_id' => $this->testRefund->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'refund_quantity' => 1,
            'unit_price' => 100.00,
            'refund_amount' => 100.00
        ]);

        $refundItem->load(['refund']);

        $this->assertTrue($refundItem->relationLoaded('refund'));
        $this->assertEquals($this->testRefund->id, $refundItem->refund->id);
    }

    public function testOrderItemRelationshipLoads(): void
    {
        $product = $this->createProduct();

        $orderItem = OrderItem::create([
            'order_id' => $this->testOrder->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total' => 200.00,
            'subtotal' => 200.00
        ]);

        $refundItem = RefundItem::create([
            'refund_id' => $this->testRefund->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'refund_quantity' => 1,
            'unit_price' => 100.00,
            'refund_amount' => 100.00
        ]);

        $refundItem->load(['orderItem']);

        $this->assertTrue($refundItem->relationLoaded('orderItem'));
        $this->assertEquals($orderItem->id, $refundItem->orderItem->id);
    }

    public function testProductRelationshipLoads(): void
    {
        $product = $this->createProduct();

        $refundItem = RefundItem::create([
            'refund_id' => $this->testRefund->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'refund_quantity' => 1,
            'unit_price' => 100.00,
            'refund_amount' => 100.00
        ]);

        $refundItem->load(['product']);

        $this->assertTrue($refundItem->relationLoaded('product'));
        $this->assertEquals($product->id, $refundItem->product->id);
    }

    public function testCastsQuantityToInteger(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 100.00,
            'stock_quantity' => 10,
            'site_id' => $this->siteId
        ]);

        $refundItem = RefundItem::create([
            'refund_id' => $this->testRefund->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => '2',
            'refund_quantity' => '1',
            'unit_price' => 100.00,
            'refund_amount' => 100.00
        ]);

        $this->assertIsInt($refundItem->quantity);
        $this->assertIsInt($refundItem->refund_quantity);
        $this->assertEquals(2, $refundItem->quantity);
        $this->assertEquals(1, $refundItem->refund_quantity);
    }

    public function testCastsUnitPriceToFloat(): void
    {
        $product = $this->createProduct();

        $refundItem = RefundItem::create([
            'refund_id' => $this->testRefund->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'refund_quantity' => 1,
            'unit_price' => '99.99',
            'refund_amount' => '99.99'
        ]);

        $this->assertIsFloat($refundItem->unit_price);
        $this->assertIsFloat($refundItem->refund_amount);
        $this->assertEquals(99.99, $refundItem->unit_price);
        $this->assertEquals(99.99, $refundItem->refund_amount);
    }

    public function testCastsTimestampsToDateTime(): void
    {
        $product = $this->createProduct();

        $refundItem = RefundItem::create([
            'refund_id' => $this->testRefund->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'refund_quantity' => 1,
            'unit_price' => 100.00,
            'refund_amount' => 100.00,
            'created_at' => '2024-01-15 10:30:00',
            'updated_at' => '2024-01-15 10:30:00'
        ]);

        $this->assertInstanceOf(\DateTime::class, $refundItem->created_at);
        $this->assertInstanceOf(\DateTime::class, $refundItem->updated_at);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = Member::create([
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'site_id' => $this->siteId
        ]);

        $this->testOrder = Order::create([
            'user_id' => $this->testUser->id,
            'order_number' => 'ORD-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 200.00,
            'site_id' => $this->siteId
        ]);

        $this->testRefund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'partial',
            'refund_amount' => 100.00,
            'reason' => 'customer_request',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $this->orderItem = $this->createOrderItem($this->testOrder->id);
    }
}