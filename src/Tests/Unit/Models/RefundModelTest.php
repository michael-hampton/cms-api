<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RefundModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $testUser;
    private Order $testOrder;

    public function testIsPendingReturnsTrueForPendingStatus(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $this->assertTrue($refund->isPending());
        $this->assertFalse($refund->isProcessed());
        $this->assertFalse($refund->isFailed());
        $this->assertFalse($refund->isCancelled());
    }

    public function testIsProcessedReturnsTrueForProcessedStatus(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        $this->assertTrue($refund->isProcessed());
        $this->assertFalse($refund->isPending());
    }

    public function testIsFailedReturnsTrueForFailedStatus(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'failed',
            'site_id' => $this->siteId
        ]);

        $this->assertTrue($refund->isFailed());
        $this->assertFalse($refund->isPending());
    }

    public function testIsCancelledReturnsTrueForCancelledStatus(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'cancelled',
            'site_id' => $this->siteId
        ]);

        $this->assertTrue($refund->isCancelled());
        $this->assertFalse($refund->isPending());
    }

    public function testOrderRelationshipLoads(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $refund->load(['order']);

        $this->assertTrue($refund->relationLoaded('order'));
        $this->assertEquals($this->testOrder->id, $refund->order->id);
    }

    public function testItemsRelationshipLoads(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'partial',
            'refund_amount' => 100.00,
            'reason' => 'customer_request',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'price' => 100.00,
            'stock_quantity' => 10,
            'site_id' => $this->siteId
        ]);

        $orderItem = $this->createOrderItem($this->testOrder->id);

        RefundItem::create([
            'refund_id' => $refund->id,
            'product_id' => $product->id,
            'order_item_id' => $orderItem->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'refund_quantity' => 1,
            'unit_price' => 100.00,
            'refund_amount' => 100.00
        ]);

        $refund->load(['items']);

        $this->assertTrue($refund->relationLoaded('items'));
        $this->assertCount(1, $refund->items);
    }

    public function testProcessedByRelationshipLoads(): void
    {
        $admin = User::create([
            'email' => 'admin@example.com',
            'name' => 'Admin User',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'admin',
            'site_id' => $this->siteId
        ]);

        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'processed_by' => $admin->id,
            'processed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $refund->load(['processedBy']);

        $this->assertTrue($refund->relationLoaded('processedBy'));
        $this->assertEquals($admin->id, $refund->processedBy->id);
    }

    public function testScopeByOrderFiltersCorrectly(): void
    {
        $order2 = Order::create([
            'user_id' => $this->testUser->id,
            'order_number' => 'ORD-002',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 150.00,
            'site_id' => $this->siteId
        ]);

        Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        Refund::create([
            'order_id' => $order2->id,
            'refund_type' => 'full',
            'refund_amount' => 150.00,
            'reason' => 'customer_request',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $refunds = Refund::byOrder($this->testOrder->id)->get();

        $this->assertCount(1, $refunds);
        $this->assertEquals($this->testOrder->id, $refunds->first()->order_id);
    }

    public function testScopeByStatusFiltersCorrectly(): void
    {
        Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'partial',
            'refund_amount' => 100.00,
            'reason' => 'damaged_item',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        $pendingRefunds = Refund::byStatus('pending')->get();
        $processedRefunds = Refund::byStatus('processed')->get();

        $this->assertGreaterThanOrEqual(1, $pendingRefunds->count());
        $this->assertGreaterThanOrEqual(1, $processedRefunds->count());

        foreach ($pendingRefunds as $refund) {
            $this->assertEquals('pending', $refund->status);
        }

        foreach ($processedRefunds as $refund) {
            $this->assertEquals('processed', $refund->status);
        }
    }

    public function testCastsRefundAmountToFloat(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => '200.50',
            'reason' => 'customer_request',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $this->assertIsFloat($refund->refund_amount);
        $this->assertEquals(200.50, $refund->refund_amount);
    }

    public function testCastsNotifyCustomerToBoolean(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'notify_customer' => 1,
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $this->assertIsBool($refund->notify_customer);
        $this->assertTrue($refund->notify_customer);
    }

    public function testCastsRestockItemsToBoolean(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'restock_items' => 0,
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $this->assertIsBool($refund->restock_items);
        $this->assertFalse($refund->restock_items);
    }

    public function testCastsProcessedAtToDateTime(): void
    {
        $refund = Refund::create([
            'order_id' => $this->testOrder->id,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'processed_at' => '2024-01-15 10:30:00',
            'site_id' => $this->siteId
        ]);

        $this->assertInstanceOf(\DateTime::class, $refund->processed_at);
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
    }
}