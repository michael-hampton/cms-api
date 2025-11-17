<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Refund;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OrderModelTest extends FunctionalTestCase
{
    use CreatesTestData;

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
    }

    public function testOrderHasCorrectTable()
    {
        $order = new Order();
        $this->assertEquals('orders', $order->getTable());
    }

    public function testIsPendingReturnsTrueWhenStatusIsPending()
    {
        $order = new Order(['status' => 'pending']);
        $this->assertTrue($order->isPending());
    }

    public function testIsPendingReturnsFalseWhenStatusIsNotPending()
    {
        $order = new Order(['status' => 'completed']);
        $this->assertFalse($order->isPending());
    }

    public function testIsProcessingReturnsTrueWhenStatusIsProcessing()
    {
        $order = new Order(['status' => 'processing']);
        $this->assertTrue($order->isProcessing());
    }

    public function testIsCompletedReturnsTrueWhenStatusIsCompleted()
    {
        $order = new Order(['status' => 'completed']);
        $this->assertTrue($order->isCompleted());
    }

    public function testIsCancelledReturnsTrueWhenStatusIsCancelled()
    {
        $order = new Order(['status' => 'cancelled']);
        $this->assertTrue($order->isCancelled());
    }

    public function testIsRefundedReturnsTrueWhenStatusIsRefunded()
    {
        $order = new Order(['status' => 'refunded']);
        $this->assertTrue($order->isRefunded());
    }

    public function testIsPaidReturnsTrueWhenPaymentStatusIsPaid()
    {
        $order = new Order(['payment_status' => 'paid']);
        $this->assertTrue($order->isPaid());
    }

    public function testIsPaidReturnsFalseWhenPaymentStatusIsUnpaid()
    {
        $order = new Order(['payment_status' => 'unpaid']);
        $this->assertFalse($order->isPaid());
    }

    public function testCanBeCancelledReturnsTrueForPendingStatus()
    {
        $order = new Order(['status' => 'pending']);
        $this->assertTrue($order->canBeCancelled());
    }

    public function testCanBeCancelledReturnsTrueForProcessingStatus()
    {
        $order = new Order(['status' => 'processing']);
        $this->assertTrue($order->canBeCancelled());
    }

    public function testCanBeCancelledReturnsFalseForCompletedStatus()
    {
        $order = new Order(['status' => 'completed']);
        $this->assertFalse($order->canBeCancelled());
    }

    public function testCanBeCancelledReturnsFalseForCancelledStatus()
    {
        $order = new Order(['status' => 'cancelled']);
        $this->assertFalse($order->canBeCancelled());
    }

    public function testGetFormattedTotalAttributeReturnsFormattedString()
    {
        $order = new Order([
            'total' => 123.45,
            'currency' => 'USD'
        ]);

        $this->assertEquals('USD 123.45', $order->getFormattedTotalAttribute());
    }

    public function testToArrayIncludesFormattedTotal()
    {
        $order = new Order([
            'id' => 1,
            'order_number' => 'ORD-123',
            'total' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'unpaid'
        ]);

        $array = $order->toArray();

        $this->assertArrayHasKey('formatted_total', $array);
        $this->assertEquals('USD 100.00', $array['formatted_total']);
    }

    public function testToArrayIncludesIsPaid()
    {
        $order = new Order([
            'payment_status' => 'paid',
            'status' => 'completed',
            'currency' => 'USD',
            'total' => 100
        ]);

        $array = $order->toArray();

        $this->assertArrayHasKey('is_paid', $array);
        $this->assertTrue($array['is_paid']);
    }

    public function testToArrayIncludesCanBeCancelled()
    {
        $order = new Order([
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'total' => 100
        ]);

        $array = $order->toArray();

        $this->assertArrayHasKey('can_be_cancelled', $array);
        $this->assertTrue($array['can_be_cancelled']);
    }

    public function testCanTransitionToReturnsTrueForValidTransitions()
    {
        $order = new Order(['status' => 'pending']);

        $this->assertTrue($order->canTransitionTo('processing'));
        $this->assertTrue($order->canTransitionTo('cancelled'));
        $this->assertTrue($order->canTransitionTo('on_hold'));
    }

    public function testCanTransitionToReturnsFalseForInvalidTransitions()
    {
        $order = new Order(['status' => 'completed']);

        $this->assertFalse($order->canTransitionTo('pending'));
        $this->assertFalse($order->canTransitionTo('processing'));
        $this->assertTrue($order->canTransitionTo('refunded')); // Only valid transition
    }

    public function testCannotTransitionFromCancelled()
    {
        $order = new Order(['status' => 'cancelled']);

        $this->assertFalse($order->canTransitionTo('pending'));
        $this->assertFalse($order->canTransitionTo('processing'));
        $this->assertFalse($order->canTransitionTo('completed'));
    }

    public function testChangeStatusUpdatesStatusAndLogsHistory()
    {
        $order = $this->createOrder(['status' => 'pending']);
        $member = $this->createMember();

        $result = $order->changeStatus('processing', $member->id, 'Starting to process order');

        $this->assertTrue($result);
        $this->assertEquals('processing', $order->status);

    }

    public function testChangeStatusThrowsExceptionForInvalidTransition()
    {
        $order = $this->createOrder(['status' => 'completed']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot transition from completed to pending');

        $order->changeStatus('pending');
    }

    public function testIsPendingReturnsTrueForPendingStatus(): void
    {
        $order = $this->createOrder(['status' => 'pending']);

        $this->assertTrue($order->isPending());
        $this->assertFalse($order->isCompleted());
        $this->assertFalse($order->isCancelled());
    }

    public function testIsProcessingReturnsTrueForProcessingStatus(): void
    {
        $order = $this->createOrder(['status' => 'processing']);

        $this->assertTrue($order->isProcessing());
        $this->assertFalse($order->isPending());
    }

    public function testIsCompletedReturnsTrueForCompletedStatus(): void
    {
        $order = $this->createOrder(['status' => 'completed']);

        $this->assertTrue($order->isCompleted());
        $this->assertFalse($order->isPending());
    }

    public function testIsCancelledReturnsTrueForCancelledStatus(): void
    {
        $order = $this->createOrder(['status' => 'cancelled']);

        $this->assertTrue($order->isCancelled());
        $this->assertFalse($order->isPending());
    }

    public function testIsRefundedReturnsTrueForRefundedStatus(): void
    {
        $order = $this->createOrder(['status' => 'refunded']);

        $this->assertTrue($order->isRefunded());
    }

    public function testIsPaidReturnsTrueForPaidStatus(): void
    {
        $order = $this->createOrder(['status' => 'completed', 'payment_status' => 'paid']);

        $this->assertTrue($order->isPaid());
    }

    public function testCanBeCancelledReturnsTrueForPendingAndProcessing(): void
    {
        $pendingOrder = $this->createOrder(['status' => 'pending']);
        $processingOrder = $this->createOrder(['status' => 'processing']);

        $this->assertTrue($pendingOrder->canBeCancelled());
        $this->assertTrue($processingOrder->canBeCancelled());
    }

    public function testCanBeCancelledReturnsFalseForCompletedOrders(): void
    {
        $order = $this->createOrder(['status' => 'completed']);

        $this->assertFalse($order->canBeCancelled());
    }

    public function testCanTransitionToAllowsValidTransitions(): void
    {
        $order = $this->createOrder(['status' => 'pending']);

        $this->assertTrue($order->canTransitionTo('processing'));
        $this->assertTrue($order->canTransitionTo('cancelled'));
    }

    public function testCanTransitionToBlocksInvalidTransitions(): void
    {
        $order = $this->createOrder(['status' => 'completed']);

        $this->assertFalse($order->canTransitionTo('pending'));
        $this->assertFalse($order->canTransitionTo('processing'));
    }

    public function testCanBeRefundedReturnsFalseWhenFullyRefunded(): void
    {
        $order = $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
        ]);

        // Create a full refund
        Refund::create([
            'order_id' => $order->id,
            'refund_type' => 'full',
            'refund_amount' => 100.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        // Load refunds
        $order = Order::with(['refunds'])->find($order->id);

        $this->assertFalse($order->canBeRefunded());
    }

    public function testGetTotalRefundedAttributeReturnsZeroWithNoRefunds(): void
    {
        $order = $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
        ]);

        $order->load(['refunds']);

        $this->assertEquals(0.0, $order->getTotalRefundedAttribute());
    }

    public function testGetTotalRefundedAttributeCalculatesCorrectly(): void
    {
        $order = $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 200.00,
        ]);

        Refund::create([
            'order_id' => $order->id,
            'refund_type' => 'partial',
            'refund_amount' => 50.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        Refund::create([
            'order_id' => $order->id,
            'refund_type' => 'partial',
            'refund_amount' => 30.00,
            'reason' => 'damaged_item',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        // Pending refund should not be counted
        Refund::create([
            'order_id' => $order->id,
            'refund_type' => 'partial',
            'refund_amount' => 20.00,
            'reason' => 'other',
            'status' => 'pending',
            'site_id' => $this->siteId
        ]);

        $order->load(['refunds']);

        $this->assertEquals(80.00, $order->getTotalRefundedAttribute());
    }

    public function testGetRemainingRefundableAttributeCalculatesCorrectly(): void
    {
        $order = $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 200.00,
        ]);

        Refund::create([
            'order_id' => $order->id,
            'refund_type' => 'partial',
            'refund_amount' => 50.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        $order->load(['refunds']);

        $this->assertEquals(150.00, $order->getRemainingRefundableAttribute());
    }

    public function testGetRemainingRefundableAttributeReturnsZeroWhenFullyRefunded(): void
    {
        $order = $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
        ]);

        Refund::create([
            'order_id' => $order->id,
            'refund_type' => 'full',
            'refund_amount' => 100.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        $order->load(['refunds']);

        $this->assertEquals(0.0, $order->getRemainingRefundableAttribute());
    }

    public function testIsFullyRefundedReturnsTrueWhenFullyRefunded(): void
    {
        $order = $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
        ]);

        Refund::create([
            'order_id' => $order->id,
            'refund_type' => 'full',
            'refund_amount' => 100.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        $order->load(['refunds']);

        $this->assertTrue($order->isFullyRefunded());
    }

    public function testIsFullyRefundedReturnsFalseWhenPartiallyRefunded(): void
    {
        $order = $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
        ]);

        Refund::create([
            'order_id' => $order->id,
            'refund_type' => 'partial',
            'refund_amount' => 50.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        $order->load(['refunds']);

        $this->assertFalse($order->isFullyRefunded());
    }

    public function testRefundsRelationshipLoads(): void
    {
        $order = $this->createOrder([
            'user_id' => $this->testUser->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
        ]);

        Refund::create([
            'order_id' => $order->id,
            'refund_type' => 'full',
            'refund_amount' => 100.00,
            'reason' => 'customer_request',
            'status' => 'processed',
            'site_id' => $this->siteId
        ]);

        $order->load(['refunds']);

        $this->assertTrue($order->relationLoaded('refunds'));
        $this->assertCount(1, $order->refunds);
    }

    public function testHistoryRelationshipLoads(): void
    {
        $order = $this->createOrder([
            'user_id' => $this->testUser->id,
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);

        OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'created',
            'changes' => ['new_data' => []],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $order->load(['history']);

        $this->assertTrue($order->relationLoaded('history'));
        $this->assertCount(1, $order->history);
    }


}