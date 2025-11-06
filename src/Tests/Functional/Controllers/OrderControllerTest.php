<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OrderControllerTest extends FunctionalTestCase
{
    use CreatesTestData;
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
       $this->createOrder();

        $response = $this->getForSite('/api/orders');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    public function testIndexWithSearchCriteria()
    {
        $this->createOrder(['status' => 'pending']);
        $this->createOrder(['status' => 'completed']);

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
        $order = $this->createOrder(['order_number' => 'ORD-TEST-001']);

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
        $order = $this->createOrder(['order_number' => 'ORD-TEST-002']);

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
        $order = $this->createOrder();
        $product = $this->createProduct();

        $updateData = [
            'status' => 'processing',
            'admin_notes' => 'Order is being processed',
            'items' => [['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 100.00]]
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
        $order = $this->createOrder();
        $this->createOrderItem($order->id);

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
        $order = $this->createOrder();

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
        $order = $this->createOrder(['status' => 'pending']);

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
        $order = $this->createOrder(['status' => 'processing']);

        $response = $this->postForSite("/api/orders/{$order->id}/complete");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('completed', $data['data']['order']['status']);
        $this->assertNotNull($data['data']['order']['completed_at']);
    }

    public function testRefundChangesOrderStatusAndPaymentStatus()
    {
        $order = $this->createOrder();

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
        $originalOrder = $this->createOrder();

        $this->createOrderItem($originalOrder->id);

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
        $this->createOrder(['status' => 'pending']);
        $this->createOrder(['status' => 'completed']);
        $this->createOrder(['status' => 'completed']);

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

        $this->createOrder(['user_id' => $this->testUser->id]);
        $this->createOrder(['user_id' => $this->testUser->id]);
        $this->createOrder(['user_id' => $anotherUser->id]);

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
            $this->createOrder(['user_id' => $this->testUser->id]);
        }

        $response = $this->getForSite("/api/orders/by-user/{$this->testUser->id}?limit=3");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(3, $data['data']['orders']);
    }

    public function testRevenueCalculatesTotalRevenue()
    {
        $this->createOrder([
            'completed_at' => '2024-01-15 10:00:00',
            'total' => 100.00,
            'status' => 'completed',
            'payment_status' => 'paid'
        ]);

        $this->createOrder([
            'completed_at' => '2024-02-20 10:00:00',
            'total' => 200.00,
            'status' => 'completed',
            'payment_status' => 'paid'
        ]);

        $this->createOrder(['status' => 'pending', 'total' => 300.00]);

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
        $this->createOrder([
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 150.00,
            'completed_at' => '2023-01-15 10:00:00',
        ]);

        $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 250.00,
            'completed_at' => '2024-01-15 10:00:00',
        ]);

        $response = $this->getForSite('/api/orders/revenue');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(400.00, $data['data']['revenue']);
    }

    public function testStoreCreatesOrderWithNewCustomer()
    {
        $orderData = [
            'customer_name' => 'New Customer',
            'customer_email' => 'newcustomer@example.com',
            'customer_phone' => '555-1234',
            'status' => 'pending',
            'shipping' => 10.00,
            'discount' => 0.00,
            'currency' => 'USD',
            'items' => [
                [
                    'product_name' => 'Test Product',
                    'product_sku' => 'TEST-001',
                    'quantity' => 1,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $response = $this->postForSite('/api/orders', $orderData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('order', $data['data']);
        $this->assertEquals('pending', $data['data']['order']['status']);

        // Verify member was created
        $member = Member::findByEmail('newcustomer@example.com', $this->siteId);
        $this->assertNotNull($member);
        $this->assertEquals('New', $member->first_name);
        $this->assertEquals('Customer', $member->last_name);

        // Verify order was linked to the new member
        $order = Order::find($data['data']['order']['id']);
        $this->assertEquals($member->id, $order->user_id);
    }

    public function testStoreUsesExistingMemberForDuplicateEmail()
    {
        // Create an existing member
        $existingMember = Member::create([
            'email' => 'existing@example.com',
            'first_name' => 'Existing',
            'last_name' => 'Member',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'site_id' => $this->siteId,
            'is_active' => true
        ]);

        $orderData = [
            'customer_name' => 'Different Name',
            'customer_email' => 'existing@example.com', // Same email
            'status' => 'pending',
            'items' => [
                [
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 50.00
                ]
            ]
        ];

        $response = $this->postForSite('/api/orders', $orderData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Verify order uses existing member
        $order = Order::find($data['data']['order']['id']);
        $this->assertEquals($existingMember->id, $order->user_id);

        // Verify no duplicate member was created
        $memberCount = Member::where('email', 'existing@example.com')
            ->where('site_id', $this->siteId)
            ->count();
        $this->assertEquals(1, $memberCount);
    }

    public function testBulkUpdateStatusSuccessfully(): void
    {
        $order1 = $this->createOrder(['status' => 'pending']);
        $order2 = $this->createOrder(['status' => 'pending']);

        $response = $this->postForSite('/api/orders/bulk-status', [
            'ids' => [$order1->id, $order2->id],
            'status' => 'processing'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['result']['updated']);
        $this->assertCount(0, $data['result']['failed']);

        // Verify in database
        $updatedOrder1 = Order::find($order1->id);
        $updatedOrder2 = Order::find($order2->id);

        $this->assertEquals('processing', $updatedOrder1->status);
        $this->assertEquals('processing', $updatedOrder2->status);
    }

    public function testBulkUpdateStatusValidation(): void
    {
        $response = $this->postForSite('/api/orders/bulk-status', [
            'ids' => [1, 2],
            'status' => 'invalid_status'
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }
}