<?php

namespace App\Tests\Unit\Services\Billing\Refunds;

use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\RefundRepository;
use App\Services\Billing\Refund\OrderStatusUpdater;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class OrderStatusUpdaterTest extends TestCase
{
    private RefundRepository $refundRepository;
    private OrderRepository $orderRepository;
    private OrderStatusUpdater $updater;

    public function testUpdatesOrderToFullyRefundedWhenTotalMatches(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(200.00);

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, [
                'status' => OrderStatus::REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ]);

        $this->updater->updateAfterRefund($order);
        $this->assertTrue(true);
    }

    public function testUpdatesOrderToFullyRefundedWhenTotalExceeds(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(250.00); // Over-refunded

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, [
                'status' => OrderStatus::REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ]);

        $this->updater->updateAfterRefund($order);
        $this->assertTrue(true);
    }

    public function testUpdatesOrderToPartiallyRefundedWhenBelowTotal(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(150.00);

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, [
                'status' => OrderStatus::PARTIALLY_REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ]);

        $this->updater->updateAfterRefund($order);
        $this->assertTrue(true);
    }

    public function testUpdatesOrderToPartiallyRefundedForSmallAmount(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(0.01); // Tiny refund

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, [
                'status' => OrderStatus::PARTIALLY_REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ]);

        $this->updater->updateAfterRefund($order);
        $this->assertTrue(true);
    }

    public function testUpdatesOrderToPartiallyRefundedWhenAlmostFull(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(199.99);

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, [
                'status' => OrderStatus::PARTIALLY_REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ]);

        $this->updater->updateAfterRefund($order);
        $this->assertTrue(true);
    }

    public function testHandlesZeroRefundAmount(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(0.0);

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, [
                'status' => OrderStatus::PARTIALLY_REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ]);

        $this->updater->updateAfterRefund($order);
        $this->assertTrue(true);
    }

    public function testHandlesDecimalPrecision(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 99.99;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(99.99);

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, [
                'status' => OrderStatus::REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ]);

        $this->updater->updateAfterRefund($order);
        $this->assertTrue(true);
    }

    public function testHandlesMultipleDifferentOrders(): void
    {
        // First order - fully refunded
        $order1 = m::mock(Order::class)->makePartial();
        $order1->id = 1;
        $order1->total = 100.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(100.00);

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, [
                'status' => OrderStatus::REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ]);

        $this->updater->updateAfterRefund($order1);

        // Second order - partially refunded
        $order2 = m::mock(Order::class)->makePartial();
        $order2->id = 2;
        $order2->total = 300.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(2)
            ->andReturn(100.00);

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(2, [
                'status' => OrderStatus::PARTIALLY_REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ]);

        $this->updater->updateAfterRefund($order2);
        $this->assertTrue(true);
    }

    public function testAlwaysSetsPaymentStatusToRefunded(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 500.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(50.00);

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, m::on(function ($data) {
                return $data['payment_status'] === PaymentStatus::REFUNDED->value;
            }));

        $this->updater->updateAfterRefund($order);
        $this->assertTrue(true);
    }

    public function testHandlesLargeOrderAmounts(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 999999.99;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(999999.99);

        $this->orderRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, [
                'status' => OrderStatus::REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value
            ]);

        $this->updater->updateAfterRefund($order);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundRepository = m::mock(RefundRepository::class);
        $this->orderRepository = m::mock(OrderRepository::class);

        $this->updater = new OrderStatusUpdater(
            $this->refundRepository,
            $this->orderRepository
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}