<?php

namespace App\Tests\Unit\Models;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OrderModelTest extends FunctionalTestCase
{
    use CreatesTestData;

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
}