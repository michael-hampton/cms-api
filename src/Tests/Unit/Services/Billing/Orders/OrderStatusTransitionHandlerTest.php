<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Billing\Order\OrderStatusTransitionHandler;
use Mockery;
use PHPUnit\Framework\TestCase;

class OrderStatusTransitionHandlerTest extends TestCase
{
    private OrderStatusTransitionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new OrderStatusTransitionHandler();
    }

    public function test_it_fills_completed_at_when_status_is_completed()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->completed_at = null;

        $data = ['status' => OrderStatus::COMPLETED->value];

        $result = $this->handler->fillStatusFields($data, $order);

        $this->assertEquals(OrderStatus::COMPLETED->value, $result['status']);
        $this->assertArrayHasKey('completed_at', $result);
        $this->assertNotNull($result['completed_at']);
    }

    public function test_it_does_not_overwrite_existing_completed_at()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->completed_at = '2025-01-01 10:00:00';

        $data = ['status' => OrderStatus::COMPLETED->value];

        $result = $this->handler->fillStatusFields($data, $order);

        $this->assertArrayNotHasKey('completed_at', $result);
    }

    public function test_it_fills_cancelled_at_when_status_is_cancelled()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->cancelled_at = null;

        $data = ['status' => OrderStatus::CANCELLED->value];

        $result = $this->handler->fillStatusFields($data, $order);

        $this->assertEquals(OrderStatus::CANCELLED->value, $result['status']);
        $this->assertArrayHasKey('cancelled_at', $result);
        $this->assertNotNull($result['cancelled_at']);
    }

    public function test_it_fills_refunded_at_and_payment_status_when_status_is_refunded()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->refunded_at = null;

        $data = ['status' => OrderStatus::REFUNDED->value];

        $result = $this->handler->fillStatusFields($data, $order);

        $this->assertEquals(OrderStatus::REFUNDED->value, $result['status']);
        $this->assertArrayHasKey('refunded_at', $result);
        $this->assertNotNull($result['refunded_at']);
        $this->assertEquals(PaymentStatus::REFUNDED->value, $result['payment_status']);
    }

    public function test_it_does_not_modify_data_for_pending_status()
    {
        $order = Mockery::mock(Order::class)->makePartial();

        $data = ['status' => OrderStatus::PENDING->value];

        $result = $this->handler->fillStatusFields($data, $order);

        $this->assertEquals($data, $result);
    }

    public function test_it_does_not_modify_data_for_processing_status()
    {
        $order = Mockery::mock(Order::class)->makePartial();

        $data = ['status' => OrderStatus::PROCESSING->value];

        $result = $this->handler->fillStatusFields($data, $order);

        $this->assertEquals($data, $result);
    }

    public function test_it_returns_unchanged_data_when_no_status_provided()
    {
        $order = Mockery::mock(Order::class)->makePartial();

        $data = ['total' => 100.00];

        $result = $this->handler->fillStatusFields($data, $order);

        $this->assertEquals($data, $result);
    }

    public function test_it_allows_transition_from_pending_to_processing()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::PENDING->value;

        $this->handler->validateTransition($order, OrderStatus::PROCESSING);

        $this->assertTrue(true); // No exception thrown
    }

    public function test_it_allows_transition_from_pending_to_completed()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::PENDING->value;

        $this->handler->validateTransition($order, OrderStatus::COMPLETED);

        $this->assertTrue(true);
    }

    public function test_it_allows_transition_from_pending_to_cancelled()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::PENDING->value;

        $this->handler->validateTransition($order, OrderStatus::CANCELLED);

        $this->assertTrue(true);
    }

    public function test_it_forbids_transition_from_pending_to_refunded()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot transition from pending to refunded');

        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::PENDING->value;

        $this->handler->validateTransition($order, OrderStatus::REFUNDED);
    }

    public function test_it_allows_transition_from_processing_to_completed()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::PROCESSING->value;

        $this->handler->validateTransition($order, OrderStatus::COMPLETED);

        $this->assertTrue(true);
    }

    public function test_it_allows_transition_from_processing_to_cancelled()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::PROCESSING->value;

        $this->handler->validateTransition($order, OrderStatus::CANCELLED);

        $this->assertTrue(true);
    }

    public function test_it_forbids_transition_from_processing_to_pending()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot transition from processing to pending');

        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::PROCESSING->value;

        $this->handler->validateTransition($order, OrderStatus::PENDING);
    }

    public function test_it_allows_transition_from_completed_to_refunded()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::COMPLETED->value;

        $this->handler->validateTransition($order, OrderStatus::REFUNDED);

        $this->assertTrue(true);
    }

    public function test_it_forbids_transition_from_completed_to_pending()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot transition from completed to pending');

        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::COMPLETED->value;

        $this->handler->validateTransition($order, OrderStatus::PENDING);
    }

    public function test_it_forbids_transition_from_cancelled_to_any_status()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot transition from cancelled to processing');

        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::CANCELLED->value;

        $this->handler->validateTransition($order, OrderStatus::PROCESSING);
    }

    public function test_it_forbids_transition_from_refunded_to_any_status()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot transition from refunded to completed');

        $order = Mockery::mock(Order::class)->makePartial();
        $order->status = OrderStatus::REFUNDED->value;

        $this->handler->validateTransition($order, OrderStatus::COMPLETED);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}