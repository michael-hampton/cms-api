<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Orders\OrderCancelledEvent;
use App\Events\Orders\OrderRefundedEvent;
use App\Events\Orders\OrderUpdatedEvent;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Model;
use App\Models\Order;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\Billing\Order\OrderAddressResolver;
use App\Services\Billing\Order\OrderStatusTransitionHandler;
use App\Services\Billing\Order\OrderUpdateService;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\OrderHistoryService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OrderUpdateServiceTest extends TestCase
{
    private OrderRepository $orderRepository;
    private OrderItemRepository $orderItemRepository;
    private MemberRepository $memberRepository;
    private OrderAddressResolver $addressResolver;
    private OrderCalculationService $calculationService;
    private OrderHistoryService $historyService;
    private OrderStatusTransitionHandler $statusHandler;
    private Database $database;
    private OrderUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepository::class);
        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->addressResolver = Mockery::mock(OrderAddressResolver::class);
        $this->calculationService = Mockery::mock(OrderCalculationService::class);
        $this->historyService = Mockery::mock(OrderHistoryService::class);
        $this->statusHandler = Mockery::mock(OrderStatusTransitionHandler::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new OrderUpdateService(
            $this->orderRepository,
            $this->orderItemRepository,
            $this->memberRepository,
            $this->addressResolver,
            $this->calculationService,
            $this->historyService,
            $this->statusHandler,
            $this->database
        );
    }

    public function test_it_updates_order_with_status_change()
    {
        //Event::fake();

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 123;
        $order->status = OrderStatus::PENDING->value;
        $order->user_id = 456;
        $order->site_id = 1;
        $order->shouldReceive('toArray')->andReturn([
            'id' => 123,
            'status' => OrderStatus::PENDING->value,
        ]);

        $updatedOrder = Mockery::mock(Order::class)->makePartial();
        $updatedOrder->id = 123;

        $updateData = [
            'status' => OrderStatus::PROCESSING->value,
        ];

        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->twice()
            ->with(123)
            ->andReturn($order);

        // Verify status transition is validated
        $this->statusHandler->shouldReceive('validateTransition')
            ->once()
            ->with($order, OrderStatus::PROCESSING);

        // Verify status fields are filled
        $this->statusHandler->shouldReceive('fillStatusFields')
            ->once()
            ->with($updateData, $order)
            ->andReturn($updateData);

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(456)
            ->andReturn(null);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(123, $updateData)
            ->andReturn($updatedOrder);

        $this->setOrderItemExpectations($updatedOrder);

        $this->historyService->shouldReceive('logUpdated')
            ->once();

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(123)
            ->andReturn($updatedOrder);

        $result = $this->service->update(123, $updateData, 1, 999);

        $this->assertEquals($updatedOrder, $result);
        //Event::assertDispatched(OrderUpdatedEvent::class);
    }

    public function test_it_updates_order_with_address_change()
    {
        //Event::fake();

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 123;
        $order->status = OrderStatus::PENDING->value;
        $order->user_id = 456;
        $order->site_id = 1;
        $order->shouldReceive('toArray')->andReturn(['id' => 123]);

        $member = Mockery::mock(Member::class);
        $updatedOrder = Mockery::mock(Order::class);

        $updateData = [
            'shipping_address' => [
                'line1' => '123 Main St',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
            ],
        ];

        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->setOrderItemExpectations($updatedOrder);

        $this->orderRepository->shouldReceive('find')
            ->twice()
            ->with(123)
            ->andReturn($order);

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(456)
            ->andReturn($member);

        // Verify address resolver is called
        $this->addressResolver->shouldReceive('resolveAddresses')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return isset($arg['shipping_address']);
            }), $member, 1);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->andReturn($updatedOrder);

        $this->historyService->shouldReceive('logUpdated')->once();
        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($updatedOrder);

        $result = $this->service->update(123, $updateData, 1, 999);
        $this->assertInstanceOf(Order::class, $result);
    }

    public function test_it_throws_exception_when_order_not_found()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->service->update(999, ['status' => OrderStatus::COMPLETED->value]);
    }

    public function test_it_updates_items_and_recalculates_totals()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 123;
        $order->shouldReceive('toArray')->andReturn(['id' => 123, 'subtotal' => 100.00]);

        $newItems = [
            ['product_id' => 1, 'quantity' => 2, 'unit_price' => 50.00],
            ['product_id' => 2, 'quantity' => 1, 'unit_price' => 75.00],
        ];

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(123)
            ->andReturn($order);

        // Verify old items are deleted
        $this->orderItemRepository->shouldReceive('deleteByOrderId')
            ->once()
            ->with(123);

        // Verify new items are created
        $this->orderItemRepository->shouldReceive('create')
            ->twice();

        // Verify totals are recalculated
        $this->calculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($newItems, Mockery::any())
            ->andReturn([
                'subtotal' => 175.00,
                'tax' => 17.50,
                'total' => 192.50,
            ]);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(123, Mockery::on(function ($arg) {
                return $arg['subtotal'] === 175.00
                    && $arg['total'] === 192.50;
            }))
            ->andReturn($order);

        $this->historyService->shouldReceive('logItemsUpdated')->once();

        $result = $this->service->updateItems(123, $newItems, 999);

        $this->assertTrue($result);
    }

    public function test_it_cancels_order()
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 123;
        $order->status = OrderStatus::PENDING->value;
        $order->user_id = null;
        $order->site_id = 1;
        $order->admin_notes = null;
        $order->shouldReceive('toArray')->andReturn(['id' => 123]);
        $order->shouldReceive('canBeCancelled')->once()->andReturn(true);

        $cancelledOrder = Mockery::mock(Order::class);

        $this->setOrderItemExpectations($cancelledOrder);

        $this->database->shouldReceive('transaction')
            ->times(3) // Once for cancel, once for update
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->atleast()->times(1)
            ->with(123)
            ->andReturn($order);

        $this->statusHandler->shouldReceive('validateTransition')->once();
        $this->statusHandler->shouldReceive('fillStatusFields')
            ->once()
            ->andReturn([
                'status' => OrderStatus::CANCELLED->value,
                'cancelled_at' => '2025-01-15 10:00:00',
            ]);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(123, Mockery::on(function ($arg) {
                return $arg['status'] === OrderStatus::CANCELLED->value
                    && isset($arg['cancelled_at']);
            }))
            ->andReturn($cancelledOrder);

        $this->historyService->shouldReceive('logUpdated')->once();
        $this->historyService->shouldReceive('logCancelled')
            ->once()
            ->with(123, 999, 'Customer request');

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->andReturn($cancelledOrder);

        $result = $this->service->cancel(123, 'Customer request', 999);

        $this->assertEquals($cancelledOrder, $result);
        //Event::assertDispatched(OrderCancelledEvent::class);
    }

    public function test_it_throws_exception_when_order_cannot_be_cancelled()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order cannot be cancelled in its current status');

        $order = Mockery::mock(Order::class);
        $order->shouldReceive('canBeCancelled')->once()->andReturn(false);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(123)
            ->andReturn($order);

        $this->service->cancel(123);
    }

    public function test_it_completes_order()
    {
        // Event::fake();

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 123;
        $order->status = OrderStatus::PROCESSING->value;
        $order->user_id = null;
        $order->site_id = 1;
        $order->shouldReceive('toArray')->andReturn(['id' => 123]);

        $completedOrder = Mockery::mock(Order::class);

        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->setOrderItemExpectations($completedOrder);

        $this->orderRepository->shouldReceive('find')
            ->twice()
            ->with(123)
            ->andReturn($order);

        $this->statusHandler->shouldReceive('validateTransition')->once();
        $this->statusHandler->shouldReceive('fillStatusFields')
            ->once()
            ->andReturn([
                'status' => OrderStatus::COMPLETED->value,
                'completed_at' => '2025-01-15 10:00:00',
            ]);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(123, Mockery::on(function ($arg) {
                return $arg['status'] === OrderStatus::COMPLETED->value
                    && isset($arg['completed_at']);
            }))
            ->andReturn($completedOrder);

        $this->historyService->shouldReceive('logUpdated')->once();
        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($completedOrder);

        $result = $this->service->complete(123, 999);

        $this->assertEquals($completedOrder, $result);
    }

    public function test_it_refunds_order()
    {
        //Event::fake();

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 123;
        $order->status = OrderStatus::COMPLETED->value;
        $order->user_id = null;
        $order->site_id = 1;
        $order->admin_notes = 'Previous notes';
        $order->shouldReceive('toArray')->andReturn(['id' => 123]);

        $refundedOrder = Mockery::mock(Order::class);

        $this->database->shouldReceive('transaction')
            ->times(3)
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->setOrderItemExpectations($refundedOrder);

        $this->orderRepository->shouldReceive('find')
            ->times(3)
            ->with(123)
            ->andReturn($order);

        $this->statusHandler->shouldReceive('validateTransition')->once();
        $this->statusHandler->shouldReceive('fillStatusFields')
            ->once()
            ->andReturn([
                'status' => OrderStatus::REFUNDED->value,
                'payment_status' => PaymentStatus::REFUNDED->value,
            ]);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(123, Mockery::on(function ($arg) {
                return $arg['status'] === OrderStatus::REFUNDED->value
                    && $arg['payment_status'] === PaymentStatus::REFUNDED->value;
            }))
            ->andReturn($refundedOrder);

        $this->historyService->shouldReceive('logUpdated')->once();
        $this->historyService->shouldReceive('logRefunded')
            ->once()
            ->with(123, 999, 'Defective product');

        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($refundedOrder);

        $result = $this->service->refund(123, 'Defective product', 999);

        $this->assertEquals($refundedOrder, $result);
        //Event::assertDispatched(OrderRefundedEvent::class);
    }

    public function test_it_wraps_updates_in_transaction()
    {
        //Event::fake();

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 123;
        $order->status = OrderStatus::PENDING->value;
        $order->user_id = null;
        $order->site_id = 1;
        $order->shouldReceive('toArray')->andReturn(['id' => 123]);

        // Verify transaction is used
        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->setOrderItemExpectations($order);

        $this->orderRepository->shouldReceive('find')->twice()->andReturn($order);
        $this->orderRepository->shouldReceive('update')->once()->andReturn($order);
        $this->historyService->shouldReceive('logUpdated')->once();
        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($order);

        $result = $this->service->update(123, ['total' => 100.00], 1, 999);
        $this->assertInstanceOf(Order::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function setOrderItemExpectations(Model $order)
    {
        $this->orderItemRepository->shouldReceive('deleteByOrderId')
            ->with(123)
            ->once();

        $orderTotals = [
            'subtotal' => 0.00,
            'tax' => 0.00,
            'total' => 0.00,
            'shipping' => 0.00,
            'discount' => 0.00,
        ];

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(123, $orderTotals)
            ->andReturn($order);

        $this->historyService->shouldReceive('logItemsUpdated')
            ->with(123, 999)
            ->once();

        $this->calculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->andReturn($orderTotals);
    }
}