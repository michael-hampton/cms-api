<?php

namespace App\Tests\Unit\Services\Billing\Refunds;

use App\Events\Refunds\RefundCreated;
use App\Listeners\Refunds\LogRefundHistory;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Billing\OrderHistoryService;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class LogRefundHistoryTest extends TestCase
{
    private OrderHistoryService $historyService;
    private LogRefundHistory $listener;

    public function testLogsRefundCreatedHistory(): void
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = 5;

        $order = m::mock(Order::class)->makePartial();
        $order->id = 100;
        $order->user_id = 42;

        $event = new RefundCreated($refund, $order, 'customer_request');

        $this->historyService
            ->shouldReceive('logRefundCreated')
            ->once()
            ->with(100, 5, 42, 'customer_request');

        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    public function testLogsHistoryWithNullUserId(): void
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = 5;

        $order = m::mock(Order::class)->makePartial();
        $order->id = 100;
        $order->user_id = null;

        $event = new RefundCreated($refund, $order, 'damaged_item');

        $this->historyService
            ->shouldReceive('logRefundCreated')
            ->once()
            ->with(100, 5, null, 'damaged_item');

        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    public function testLogsHistoryWithDifferentReasons(): void
    {
        $reasons = [
            'customer_request',
            'damaged_item',
            'wrong_item',
            'out_of_stock',
            'quality_issue',
            'late_delivery'
        ];

        foreach ($reasons as $reason) {
            $refund = m::mock(Refund::class)->makePartial();
            $refund->id = 1;

            $order = m::mock(Order::class)->makePartial();
            $order->id = 100;
            $order->user_id = 42;

            $event = new RefundCreated($refund, $order, $reason);

            $this->historyService
                ->shouldReceive('logRefundCreated')
                ->once()
                ->with(100, 1, 42, $reason);

            $this->listener->handle($event);
            $this->assertTrue(true);
        }
    }

    public function testLogsHistoryForDifferentOrders(): void
    {
        $orderIds = [100, 200, 300];

        foreach ($orderIds as $orderId) {
            $refund = m::mock(Refund::class)->makePartial();
            $refund->id = 1;

            $order = m::mock(Order::class)->makePartial();
            $order->id = $orderId;
            $order->user_id = 42;

            $event = new RefundCreated($refund, $order, 'customer_request');

            $this->historyService
                ->shouldReceive('logRefundCreated')
                ->once()
                ->with($orderId, 1, 42, 'customer_request');

            $this->listener->handle($event);
            $this->assertTrue(true);
        }
    }

    public function testLogsHistoryForDifferentRefunds(): void
    {
        $refundIds = [1, 2, 3];

        foreach ($refundIds as $refundId) {
            $refund = m::mock(Refund::class)->makePartial();
            $refund->id = $refundId;

            $order = m::mock(Order::class)->makePartial();
            $order->id = 100;
            $order->user_id = 42;

            $event = new RefundCreated($refund, $order, 'customer_request');

            $this->historyService
                ->shouldReceive('logRefundCreated')
                ->once()
                ->with(100, $refundId, 42, 'customer_request');

            $this->listener->handle($event);
            $this->assertTrue(true);
        }
    }

    public function testExtractsDataFromEventCorrectly(): void
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = 999;

        $order = m::mock(Order::class)->makePartial();
        $order->id = 888;
        $order->user_id = 777;

        $event = new RefundCreated($refund, $order, 'fraud');

        $this->historyService
            ->shouldReceive('logRefundCreated')
            ->once()
            ->with(888, 999, 777, 'fraud');

        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->historyService = m::mock(OrderHistoryService::class);
        $this->listener = new LogRefundHistory($this->historyService);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}