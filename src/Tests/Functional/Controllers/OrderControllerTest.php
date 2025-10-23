<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

class OrderControllerTest extends FunctionalTestCase
{
    private Member $testUser;

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
    }

    // EXISTING TESTS (keeping for reference)
    public function testIndexReturnsOrdersList()
    {
        Order::create([
            'order_number' => 'ORD-001',
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/orders');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    public function testIndexWithSearchCriteria()
    {
        Order::create([
            'order_number' => 'ORD-001',
            'status' => 'pending',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);
        Order::create([
            'order_number' => 'ORD-002',
            'status' => 'completed',
            'total' => 200.00,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/orders?status=completed');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertEquals('completed', $data['items'][0]['status']);
    }

    // NEW FUNCTIONAL TESTS

    public function testStoreCreatesOrderWithItems()
    {
        $orderData = [
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'shipping' => 10.00,
            'discount' => 5.00,
            'currency' => 'USD',
            'items' => [
                [
                    'product_name' => 'Test Product',
                    'product_sku' => 'TEST-001',
                    'quantity' => 2,
                    'unit_price' => 50.00
                ]
            ]
        ];

        $response = $this->postForSite('/api/orders', $orderData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('order', $data['data']);
        $this->assertEquals('pending', $data['data']['order']['status']);
        $this->assertNotEmpty($data['data']['order']['order_number']);

        // Verify order was created in database
        $order = Order::where('user_id', $this->testUser->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(105.00, $order->total); // 100 subtotal + 10 shipping - 5 discount
    }

    public function testStoreFailsWithInvalidData()
    {
        $orderData = [
            'status' => 'pending',
            // Missing required fields like user_id and items
        ];

        $response = $this->postForSite('/api/orders', $orderData);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testShowReturnsOrderById()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-001',
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/orders/{$order->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('order', $data['data']);
        $this->assertEquals($order->id, $data['data']['order']['id']);
        $this->assertEquals('ORD-TEST-001', $data['data']['order']['order_number']);
    }

    public function testShowReturnsOrderByNumber()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-002',
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'total' => 150.00,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/orders/ORD-TEST-002");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($order->id, $data['data']['order']['id']);
        $this->assertEquals('ORD-TEST-002', $data['data']['order']['order_number']);
    }

    public function testShowReturns404ForNonExistentOrder()
    {
        $response = $this->getForSite("/api/orders/99999");

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Order not found', $data['error']);
    }

    public function testUpdateModifiesOrder()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-003',
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        $updateData = [
            'status' => 'processing',
            'admin_notes' => 'Order is being processed'
        ];

        $response = $this->putForSite("/api/orders/{$order->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('processing', $data['data']['order']['status']);

        // Verify in database
        $updatedOrder = Order::find($order->id);
        $this->assertEquals('processing', $updatedOrder->status);
    }

    public function testUpdateItemsReplacesOrderItems()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-004',
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'subtotal' => 100.00,
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Old Product',
            'product_sku' => 'OLD-001',
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'total' => 100.00
        ]);

        $updateData = [
            'items' => [
                [
                    'product_name' => 'New Product 1',
                    'product_sku' => 'NEW-001',
                    'quantity' => 2,
                    'unit_price' => 50.00
                ],
                [
                    'product_name' => 'New Product 2',
                    'product_sku' => 'NEW-002',
                    'quantity' => 1,
                    'unit_price' => 75.00
                ]
            ]
        ];

        $response = $this->putForSite("/api/orders/{$order->id}/items", $updateData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Verify items were updated
        $items = OrderItem::where('order_id', $order->id)->get();
        $this->assertCount(2, $items);

        // Verify totals were recalculated
        $this->assertEquals(175.00, $data['data']['order']['subtotal']);
    }

    public function testDestroyDeletesOrder()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-005',
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        $orderId = $order->id;

        $response = $this->deleteForSite("/api/orders/{$orderId}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Order deleted successfully', $data['message']);

        // Verify order was deleted
        $deletedOrder = Order::find($orderId);
        $this->assertNull($deletedOrder);
    }

    public function testDestroyReturns404ForNonExistentOrder()
    {
        $response = $this->deleteForSite("/api/orders/99999");

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Order not found', $data['error']);
    }

    public function testCancelChangesOrderStatus()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-006',
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        $cancelData = [
            'reason' => 'Customer requested cancellation'
        ];

        $response = $this->postForSite("/api/orders/{$order->id}/cancel", $cancelData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('cancelled', $data['data']['order']['status']);
        $this->assertNotNull($data['data']['order']['cancelled_at']);

        // Verify in database
        $cancelledOrder = Order::find($order->id);
        $this->assertEquals('cancelled', $cancelledOrder->status);
        $this->assertStringContainsString('Customer requested cancellation', $cancelledOrder->admin_notes);
    }

    public function testCompleteChangesOrderStatus()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-007',
            'user_id' => $this->testUser->id,
            'status' => 'processing',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/orders/{$order->id}/complete");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('completed', $data['data']['order']['status']);
        $this->assertNotNull($data['data']['order']['completed_at']);
    }

    public function testRefundChangesOrderStatusAndPaymentStatus()
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-008',
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        $refundData = [
            'reason' => 'Product defect'
        ];

        $response = $this->postForSite("/api/orders/{$order->id}/refund", $refundData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('refunded', $data['data']['order']['status']);
        $this->assertEquals('refunded', $data['data']['order']['payment_status']);

        // Verify in database
        $refundedOrder = Order::find($order->id);
        $this->assertStringContainsString('Product defect', $refundedOrder->admin_notes);
    }

    public function testDuplicateCreatesNewOrder()
    {
        $originalOrder = Order::create([
            'order_number' => 'ORD-TEST-009',
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'subtotal' => 100.00,
            'total' => 100.00,
            'currency' => 'USD',
            'site_id' => $this->siteId
        ]);

        OrderItem::create([
            'order_id' => $originalOrder->id,
            'product_name' => 'Test Product',
            'product_sku' => 'TEST-001',
            'quantity' => 2,
            'unit_price' => 50.00,
            'subtotal' => 100.00,
            'total' => 100.00
        ]);

        $response = $this->postForSite("/api/orders/{$originalOrder->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Verify new order was created with pending status
        $this->assertNotEquals($originalOrder->id, $data['data']['id']);
        $this->assertNotEquals($originalOrder->order_number, $data['data']['order_number']);
        $this->assertEquals('pending', $data['data']['status']);
        $this->assertEquals('unpaid', $data['data']['payment_status']);
        $this->assertEquals($originalOrder->user_id, $data['data']['user_id']);

        // Verify items were copied
        $duplicatedOrder = Order::find($data['data']['id']);
        $this->assertNotNull($duplicatedOrder);
        $items = OrderItem::where('order_id', $duplicatedOrder->id)->get();
        $this->assertCount(1, $items);
    }

    public function testDuplicateReturns404ForNonExistentOrder()
    {
        $response = $this->postForSite("/api/orders/99999/duplicate");

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('not found', $data['error']);
    }

    public function testByStatusReturnsFilteredOrders()
    {
        Order::create([
            'order_number' => 'ORD-010',
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        Order::create([
            'order_number' => 'ORD-011',
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'total' => 200.00,
            'site_id' => $this->siteId
        ]);

        Order::create([
            'order_number' => 'ORD-012',
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'total' => 300.00,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/orders/by-status?status=completed');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('orders', $data['data']);
        $this->assertCount(2, $data['data']['orders']);

        foreach ($data['data']['orders'] as $order) {
            $this->assertEquals('completed', $order['status']);
        }
    }

    public function testByStatusRequiresStatusParameter()
    {
        $response = $this->getForSite('/api/orders/by-status');

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Status parameter is required', $data['error']);
    }

    public function testByUserReturnsUserOrders()
    {
        $anotherUser = Member::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'site_id' => $this->siteId
        ]);

        Order::create([
            'order_number' => 'ORD-013',
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        Order::create([
            'order_number' => 'ORD-014',
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'total' => 200.00,
            'site_id' => $this->siteId
        ]);

        Order::create([
            'order_number' => 'ORD-015',
            'user_id' => $anotherUser->id,
            'status' => 'pending',
            'total' => 300.00,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/orders/by-user/{$this->testUser->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('orders', $data['data']);
        $this->assertCount(2, $data['data']['orders']);

        foreach ($data['data']['orders'] as $order) {
            $this->assertEquals($this->testUser->id, $order['user_id']);
        }
    }

    public function testByUserRespectsLimitParameter()
    {
        for ($i = 1; $i <= 5; $i++) {
            Order::create([
                'order_number' => "ORD-01{$i}",
                'user_id' => $this->testUser->id,
                'status' => 'pending',
                'total' => 100.00 * $i,
                'site_id' => $this->siteId
            ]);
        }

        $response = $this->getForSite("/api/orders/by-user/{$this->testUser->id}?limit=3");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(3, $data['data']['orders']);
    }

    public function testRevenueCalculatesTotalRevenue()
    {
        Order::create([
            'order_number' => 'ORD-016',
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
            'completed_at' => '2024-01-15 10:00:00',
            'site_id' => $this->siteId
        ]);

        Order::create([
            'order_number' => 'ORD-017',
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 200.00,
            'completed_at' => '2024-02-20 10:00:00',
            'site_id' => $this->siteId
        ]);

        Order::create([
            'order_number' => 'ORD-018',
            'user_id' => $this->testUser->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 300.00,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/orders/revenue?start_date=2024-01-01&end_date=2024-12-31');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('revenue', $data['data']);
        $this->assertEquals(300.00, $data['data']['revenue']); // Only completed + paid orders
        $this->assertEquals('2024-01-01', $data['data']['start_date']);
        $this->assertEquals('2024-12-31', $data['data']['end_date']);
    }

    public function testRevenueWithoutDateRangeCalculatesAllRevenue()
    {
        Order::create([
            'order_number' => 'ORD-019',
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 150.00,
            'completed_at' => '2023-01-15 10:00:00',
            'site_id' => $this->siteId
        ]);

        Order::create([
            'order_number' => 'ORD-020',
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 250.00,
            'completed_at' => '2024-01-15 10:00:00',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/orders/revenue');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(400.00, $data['data']['revenue']);
    }
}