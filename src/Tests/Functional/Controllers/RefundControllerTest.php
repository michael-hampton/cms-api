<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RefundControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $testUser;
    private Order $testOrder;

    public function testStoreCreatesFullRefund(): void
    {
        $refundData = [
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'internal_notes' => 'Customer requested full refund',
            'notify_customer' => true,
            'restock_items' => true
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('refund', $data['data']);
        $this->assertEquals(200.00, $data['data']['refund']['refund_amount']);
        $this->assertEquals('full', $data['data']['refund']['refund_type']);
        $this->assertEquals('processed', $data['data']['refund']['status']);

        // Verify in database
        $refund = Refund::where('order_id', $this->testOrder->id)->first();
        $this->assertNotNull($refund);
        $this->assertEquals(200.00, $refund->refund_amount);
    }

    public function testStoreCreatesPartialRefund(): void
    {
        $product = $this->createProduct(['sku' => 'TEST-SKU']);
        $orderItem = OrderItem::where('order_id', $this->testOrder->id)->first();

        $refundData = [
            'order_id' => $this->testOrder->id,
            'refund_type' => 'partial',
            'refund_amount' => 100.00,
            'reason' => 'damaged_item',
            'items' => [
                [
                    'id' => $orderItem->id,
                    'product_id' => $product->id,
                    'product_name' => 'Test Product',
                    'quantity' => 2,
                    'refund_quantity' => 1,
                    'price' => 100.00,
                    'refund_amount' => 100.00
                ]
            ],
            'notify_customer' => true,
            'restock_items' => true
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(100.00, $data['data']['refund']['refund_amount']);
        $this->assertEquals('partial', $data['data']['refund']['refund_type']);

        // Verify refund items were created
        $refund = Refund::where('order_id', $this->testOrder->id)->first();
        $refundItems = RefundItem::where('refund_id', $refund->id)->get();
        $this->assertCount(1, $refundItems);
        $this->assertEquals(1, $refundItems->first()->refund_quantity);
    }

    public function testStoreRestocksItemsWhenRequested(): void
    {
        $product = $this->createProduct([
            'sku' => 'TEST-SKU',
            'stock_quantity' => 10
        ]);

        $orderItem = OrderItem::where('order_id', $this->testOrder->id)->first();

        $refundData = [
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'items' => [
                [
                    'id' => $orderItem->id,
                    'product_id' => $product->id,
                    'product_name' => 'Test Product',
                    'quantity' => 2,
                    'refund_quantity' => 2,
                    'price' => 100.00,
                    'refund_amount' => 200.00
                ]
            ],
            'notify_customer' => false,
            'restock_items' => true
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(201, $response->getStatusCode());

        // Verify stock was increased
        $updatedProduct = Product::find($product->id);
        $this->assertEquals(12, $updatedProduct->stock_quantity);
    }

    public function testStoreDoesNotRestockWhenNotRequested(): void
    {
        $product = $this->createProduct([
            'sku' => 'TEST-SKU',
            'stock_quantity' => 10
        ]);

        $orderItem = OrderItem::where('order_id', $this->testOrder->id)->first();

        $refundData = [
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'items' => [
                [
                    'id' => $orderItem->id,
                    'product_id' => $product->id,
                    'product_name' => 'Test Product',
                    'quantity' => 2,
                    'refund_quantity' => 2,
                    'price' => 100.00,
                    'refund_amount' => 200.00
                ]
            ],
            'restock_items' => false
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(201, $response->getStatusCode());

        // Verify stock was NOT increased
        $updatedProduct = Product::find($product->id);
        $this->assertEquals(10, $updatedProduct->stock_quantity);
    }

    public function testStoreUpdatesOrderStatusWhenFullyRefunded(): void
    {
        $refundData = [
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'notify_customer' => false,
            'restock_items' => false
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(201, $response->getStatusCode());

        // Verify order status was updated
        $updatedOrder = Order::find($this->testOrder->id);
        $this->assertEquals('refunded', $updatedOrder->status);
        $this->assertEquals('refunded', $updatedOrder->payment_status);
    }

    public function testStoreFailsForNonExistentOrder(): void
    {
        $refundData = [
            'order_id' => 99999,
            'refund_type' => 'full',
            'refund_amount' => 100.00,
            'reason' => 'customer_request'
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Order not found', $data['error']);
    }

    public function testStoreFailsForUnpaidOrder(): void
    {
        $unpaidOrder = $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 100.00
        ]);

        $refundData = [
            'order_id' => $unpaidOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 100.00,
            'reason' => 'customer_request'
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('cannot be refunded', $data['error']);
    }

    public function testStoreFailsWhenRefundExceedsOrderTotal(): void
    {
        $refundData = [
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 300.00, // More than order total
            'reason' => 'customer_request'
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('exceeds remaining order total', $data['error']);
    }

    public function testStoreFailsWhenRefundExceedsRemainingAmount(): void
    {
        // Create first refund
        Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'partial',
            'refund_amount' => 150.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        // Try to refund more than remaining
        $refundData = [
            'order_id' => $this->testOrder->id,
            'refund_type' => 'partial',
            'refund_amount' => 100.00, // Only 50 left
            'reason' => 'customer_request'
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('exceeds remaining order total', $data['error']);
    }

    public function testIndexReturnsRefundsForOrder(): void
    {
        // Create multiple refunds
        Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'partial',
            'refund_amount' => 100.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'partial',
            'refund_amount' => 50.00,
            'reason' => 'damaged_item',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/orders/{$this->testOrder->id}/refunds");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('refunds', $data['data']);
        $this->assertCount(2, $data['data']['refunds']);
    }

    public function testCancelCancelsRefund(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/refunds/{$refund->id}/cancel");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Refund cancelled successfully', $data['data']['message']);

        // Verify status was updated
        $updatedRefund = Refund::find($refund->id);
        $this->assertEquals('cancelled', $updatedRefund->status);
    }

    public function testCancelFailsForProcessedRefund(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/refunds/{$refund->id}/cancel");

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Only pending refunds can be cancelled', $data['error']);
    }

    public function testRemainingAmountReturnsCorrectAmount(): void
    {
        // Create a partial refund
        Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'partial',
            'refund_amount' => 150.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/orders/{$this->testOrder->id}/refunds/remaining");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('remaining_amount', $data['data']);
        $this->assertEquals(50.00, $data['data']['remaining_amount']);
    }

    public function testStoreValidatesRequiredFields(): void
    {
        $refundData = [
            'order_id' => $this->testOrder->id,
            // Missing refund_type, refund_amount, reason
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
    }

    public function testStoreCreatesOrderHistory(): void
    {
        $refundData = [
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'notify_customer' => false,
            'restock_items' => false
        ];

        $response = $this->postForSite('/api/refunds', $refundData);

        $this->assertEquals(201, $response->getStatusCode());

        // Verify history was created
        $history = \App\Models\OrderHistory::where('order_id', $this->testOrder->id)
            ->where('action', 'refund_created')
            ->first();

        $this->assertNotNull($history);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = Member::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'site_id' => $this->siteId
        ]);

        $this->testOrder = $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 200.00
        ]);

        $this->createOrderItem($this->testOrder->id, [
            'product_name' => 'Test Product',
            'quantity' => 2,
            'unit_price' => 100.00,
            'total' => 200.00
        ]);
    }
}