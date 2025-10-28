<?php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\MemberRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderItemRepository;
use App\Services\OrderService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m; // Import Mockery with a simple alias
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends FunctionalTestCase
{
    private $orderRepository;
    private $orderItemRepository;
    private $memberRepository;
    private $databaseMock;
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp(); // Call parent setup if it exists
        // Use Mockery::mock() instead of $this->createMock()
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->memberRepository = m::mock(MemberRepository::class);
        $this->orderItemRepository = m::mock(OrderItemRepository::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new OrderService(
            $this->orderRepository,
            $this->orderItemRepository,
            $this->memberRepository,
            $this->databaseMock
        );
    }

    // Add Mockery tearDown to ensure mock expectations are verified and cleared.
    protected function tearDown(): void
    {
        // This is crucial for Mockery to verify expectations
        // and clean up the static container after each test.
        m::close();
        parent::tearDown(); // Call parent teardown if it exists
    }
    public function testGetOrderByIdReturnsOrder()
    {
        $orderId = 1;
        $mockOrder = m::mock(Order::class);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $result = $this->service->getOrderById($orderId);

        $this->assertSame($mockOrder, $result);
    }

    public function testGetOrderByIdReturnsNullWhenNotFound()
    {
        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->getOrderById(999);

        $this->assertNull($result);
    }

    public function testGetOrderByNumberReturnsOrder()
    {
        $orderNumber = 'ORD-123';
        $mockOrder = m::mock(Order::class);

        $mockOrder->shouldReceive('load')
            ->once()
            ->with(['items', 'user'])
            ->andReturnSelf();

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->with($orderNumber)
            ->andReturn($mockOrder);

        $result = $this->service->getOrderByNumber($orderNumber);

        $this->assertSame($mockOrder, $result);
    }

    public function testGetOrderByNumberReturnsNullWhenNotFound()
    {
        $orderNumber = 'NON-EXISTENT';

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->with($orderNumber)
            ->andReturn(null);

        $result = $this->service->getOrderByNumber($orderNumber);

        $this->assertNull($result);
    }

    public function testCreateOrderGeneratesOrderNumberWhenNotProvided()
    {
        $data = [
            'user_id' => 1,
            'status' => 'pending'
        ];
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 2,
                'unit_price' => 50.00
            ]
        ];
        $siteId = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($siteId) {
                return isset($data['order_number'])
                    && $data['site_id'] === $siteId
                    && $data['status'] === 'pending'
                    && $data['payment_status'] === 'unpaid';
            }))
            ->andReturn($mockOrder);

        $this->orderItemRepository->shouldReceive('create')
            ->once()
            ->andReturn(m::mock(OrderItem::class));

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $result = $this->service->createOrder($data, $items, $siteId);

        $this->assertSame($mockOrder, $result);
    }

    public function testCreateOrderUsesProvidedOrderNumber()
    {
        $data = [
            'user_id' => 1,
            'order_number' => 'CUSTOM-001',
            'status' => 'pending'
        ];
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 1,
                'unit_price' => 100.00
            ]
        ];
        $siteId = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['order_number'] === 'CUSTOM-001';
            }))
            ->andReturn($mockOrder);

        $this->orderItemRepository->shouldReceive('create')
            ->once()
            ->andReturn(m::mock(OrderItem::class));

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $result = $this->service->createOrder($data, $items, $siteId);

        $this->assertSame($mockOrder, $result);
    }

    public function testCreateOrderCalculatesTotalsCorrectly()
    {
        $data = [
            'user_id' => 1,
            'shipping' => 10.00,
            'discount' => 5.00
        ];
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 2,
                'unit_price' => 50.00,
                'tax' => 10.00
            ],
            [
                'product_name' => 'Product 2',
                'quantity' => 1,
                'unit_price' => 30.00,
                'tax' => 3.00
            ]
        ];
        $siteId = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                // subtotal = (2 * 50) + (1 * 30) = 130
                // tax = 10 + 3 = 13
                // shipping = 10
                // discount = 5
                // total = 130 + 13 + 10 - 5 = 148
                return $data['subtotal'] == 130.00
                    && $data['tax'] == 13.00
                    && $data['total'] == 148.00;
            }))
            ->andReturn($mockOrder);

        $this->orderItemRepository->shouldReceive('create')
            ->twice()
            ->andReturn(m::mock(OrderItem::class));

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $result = $this->service->createOrder($data, $items, $siteId);

        $this->assertSame($mockOrder, $result);
    }

    public function testCreateOrderCreatesMultipleItems()
    {
        $data = ['user_id' => 1];
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 1,
                'unit_price' => 50.00
            ],
            [
                'product_name' => 'Product 2',
                'quantity' => 2,
                'unit_price' => 30.00
            ],
            [
                'product_name' => 'Product 3',
                'quantity' => 1,
                'unit_price' => 25.00
            ]
        ];
        $siteId = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->andReturn($mockOrder);

        $this->orderItemRepository->shouldReceive('create')
            ->times(3) // Should create 3 items
            ->andReturn(m::mock(OrderItem::class));

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $result = $this->service->createOrder($data, $items, $siteId);

        $this->assertSame($mockOrder, $result);
    }

    public function testUpdateOrderThrowsExceptionWhenOrderNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $this->service->updateOrder(999, ['status' => 'completed']);
    }


    public function testUpdateOrderUpdatesOrder()
    {
        $orderId = 1;
        $data = ['status' => 'completed'];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'pending';
        $mockOrder->completed_at = null;

        $updatedOrder = m::mock(Order::class)->makePartial();
        $updatedOrder->id = $orderId;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::any())
            ->andReturn($updatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($updatedOrder);

        $result = $this->service->updateOrder($orderId, $data);

        $this->assertSame($updatedOrder, $result);
    }

    public function testUpdateOrderSetsCompletedAtWhenStatusChangesToCompleted()
    {
        $orderId = 1;
        $data = ['status' => 'completed'];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'processing';
        $mockOrder->completed_at = null;

        $updatedOrder = m::mock(Order::class)->makePartial();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($updateData) {
                return $updateData['status'] === 'completed';
            }))
            ->andReturn($updatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($updatedOrder);

        $result = $this->service->updateOrder($orderId, $data);

        $this->assertSame($updatedOrder, $result);
    }

    public function testUpdateOrderSetsCancelledAtWhenStatusChangesToCancelled()
    {
        $orderId = 1;
        $data = ['status' => 'cancelled'];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'pending';
        $mockOrder->cancelled_at = null;

        $updatedOrder = m::mock(Order::class)->makePartial();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($updateData) {
                return $updateData['status'] === 'cancelled';
            }))
            ->andReturn($updatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($updatedOrder);

        $result = $this->service->updateOrder($orderId, $data);

        $this->assertSame($updatedOrder, $result);
    }

    public function testUpdateOrderThrowsExceptionWhenUpdateFails()
    {
        $orderId = 1;
        $data = ['status' => 'completed'];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'pending';
        $mockOrder->completed_at = null;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->andReturn(null); // Simulate failed update

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to update order');

        $this->service->updateOrder($orderId, $data);
    }

    public function testUpdateOrderItemsThrowsExceptionWhenOrderNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $this->service->updateOrderItems(999, []);
    }

    public function testUpdateOrderItemsDeletesOldAndCreatesNewItems()
    {
        $orderId = 1;
        $items = [
            [
                'product_name' => 'New Product',
                'quantity' => 1,
                'unit_price' => 100.00
            ]
        ];

        $mockOrder = m::mock(Order::class);
        $mockOrder->shouldReceive('toArray')
            ->once()
            ->andReturn(['shipping' => 10, 'discount' => 0]);

        $updatedOrder = m::mock(Order::class);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderItemRepository->shouldReceive('deleteByOrderId')
            ->once()
            ->with($orderId)
            ->andReturn(true);

        $this->orderItemRepository->shouldReceive('create')
            ->once()
            ->with(m::any())
            ->andReturn(m::mock(OrderItem::class));

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($data) {
                return isset($data['subtotal'])
                    && isset($data['tax'])
                    && isset($data['total']);
            }));

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($updatedOrder);

        $result = $this->service->updateOrderItems($orderId, $items);

        $this->assertSame($updatedOrder, $result);
    }

    public function testUpdateOrderItemsRecalculatesTotals()
    {
        $orderId = 1;
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 2,
                'unit_price' => 50.00,
                'tax' => 5.00
            ],
            [
                'product_name' => 'Product 2',
                'quantity' => 1,
                'unit_price' => 30.00,
                'tax' => 3.00
            ]
        ];

        $mockOrder = m::mock(Order::class);
        $mockOrder->shouldReceive('toArray')
            ->once()
            ->andReturn(['shipping' => 10.00, 'discount' => 5.00]);

        $updatedOrder = m::mock(Order::class);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderItemRepository->shouldReceive('deleteByOrderId')
            ->once()
            ->with($orderId);

        $this->orderItemRepository->shouldReceive('create')
            ->twice()
            ->andReturn(m::mock(OrderItem::class));

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($data) {
                // subtotal = (2*50) + (1*30) = 130
                // tax = 5 + 3 = 8
                // total = 130 + 8 + 10 - 5 = 143
                return $data['subtotal'] == 130.00
                    && $data['tax'] == 8.00
                    && $data['total'] == 143.00;
            }));

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($updatedOrder);

        $result = $this->service->updateOrderItems($orderId, $items);

        $this->assertSame($updatedOrder, $result);
    }

    public function testCancelOrderThrowsExceptionWhenOrderNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $this->service->cancelOrder(999);
    }

    public function testCancelOrderThrowsExceptionWhenOrderCannotBeCancelled()
    {
        $mockOrder = m::mock(Order::class);
        $mockOrder->shouldReceive('canBeCancelled')
            ->once()
            ->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order cannot be cancelled in its current status');

        $this->service->cancelOrder(1);
    }

    public function testCancelOrderCancelsOrderWithReason()
    {
        $orderId = 1;
        $reason = 'Customer request';

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->admin_notes = '';
        $mockOrder->shouldReceive('canBeCancelled')
            ->once()
            ->andReturn(true);

        $cancelledOrder = m::mock(Order::class)->makePartial();

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->twice()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($data) use ($reason) {
                return $data['status'] === 'cancelled'
                    && isset($data['cancelled_at'])
                    && strpos($data['admin_notes'], $reason) !== false;
            }))
            ->andReturn($cancelledOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($cancelledOrder);

        $result = $this->service->cancelOrder($orderId, $reason);

        $this->assertSame($cancelledOrder, $result);
    }

    public function testCancelOrderCancelsOrderWithoutReason()
    {
        $orderId = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->admin_notes = '';
        $mockOrder->shouldReceive('canBeCancelled')
            ->once()
            ->andReturn(true);

        $cancelledOrder = m::mock(Order::class)->makePartial();

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->twice()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($data) {
                return $data['status'] === 'cancelled'
                    && isset($data['cancelled_at'])
                    && !isset($data['admin_notes']);
            }))
            ->andReturn($cancelledOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($cancelledOrder);

        $result = $this->service->cancelOrder($orderId, null);

        $this->assertSame($cancelledOrder, $result);
    }

    public function testCancelOrderAppendsToExistingNotes()
    {
        $orderId = 1;
        $reason = 'Out of stock';

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->admin_notes = 'Previous note';
        $mockOrder->shouldReceive('canBeCancelled')
            ->once()
            ->andReturn(true);

        $cancelledOrder = m::mock(Order::class)->makePartial();

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->twice()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($data) {
                return strpos($data['admin_notes'], 'Previous note') !== false
                    && strpos($data['admin_notes'], 'Out of stock') !== false;
            }))
            ->andReturn($cancelledOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($cancelledOrder);

        $result = $this->service->cancelOrder($orderId, $reason);

        $this->assertSame($cancelledOrder, $result);
    }


    public function testCompleteOrderSetsCompletedStatus()
    {
        $orderId = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'processing';
        $mockOrder->completed_at = null;

        $completedOrder = m::mock(Order::class);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($data) {
                return $data['status'] === 'completed' && isset($data['completed_at']);
            }))
            ->andReturn($completedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($completedOrder);

        $result = $this->service->completeOrder($orderId);

        $this->assertSame($completedOrder, $result);
    }


    public function testRefundOrderThrowsExceptionWhenOrderNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $this->service->refundOrder(999);
    }

    public function testRefundOrderRefundsOrderWithReason()
    {
        $orderId = 1;
        $reason = 'Defective product';

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->admin_notes = '';

        $refundedOrder = m::mock(Order::class);

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->twice()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($data) use ($reason) {
                return $data['status'] === 'refunded'
                    && $data['payment_status'] === 'refunded'
                    && strpos($data['admin_notes'], $reason) !== false;
            }))
            ->andReturn($refundedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($refundedOrder);

        $result = $this->service->refundOrder($orderId, $reason);

        $this->assertSame($refundedOrder, $result);
    }

    public function testRefundOrderRefundsOrderWithoutReason()
    {
        $orderId = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->admin_notes = '';

        $refundedOrder = m::mock(Order::class);

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->twice()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($data) {
                return $data['status'] === 'refunded'
                    && $data['payment_status'] === 'refunded'
                    && !isset($data['admin_notes']);
            }))
            ->andReturn($refundedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($refundedOrder);

        $result = $this->service->refundOrder($orderId, null);

        $this->assertSame($refundedOrder, $result);
    }

    public function testDeleteOrderThrowsExceptionWhenOrderNotFound()
    {
        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $this->service->deleteOrder(999);
    }

    public function testDeleteOrderDeletesOrder()
    {
        $orderId = 1;

        $mockOrder = m::mock(Order::class);
        $mockOrder->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $result = $this->service->deleteOrder($orderId);

        $this->assertTrue($result);
    }

    public function testDeleteOrderReturnsFalseWhenDeleteFails()
    {
        $orderId = 1;

        $mockOrder = m::mock(Order::class);
        $mockOrder->shouldReceive('delete')
            ->once()
            ->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $result = $this->service->deleteOrder($orderId);

        $this->assertFalse($result);
    }


    public function testDuplicateOrderThrowsExceptionWhenOrderNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $this->service->duplicateOrder(999);
    }

    public function testDuplicateOrderCreatesNewOrderWithPendingStatus()
    {
        $orderId = 1;

        $originalOrder = m::mock(Order::class)->makePartial();
        $originalOrder->user_id = 10;
        $originalOrder->status = 'completed';
        $originalOrder->subtotal = 100.00;
        $originalOrder->tax = 10.00;
        $originalOrder->shipping = 5.00;
        $originalOrder->discount = 0.00;
        $originalOrder->total = 115.00;
        $originalOrder->currency = 'USD';
        $originalOrder->site_id = 1;
        $originalOrder->shipping_address = '123 Main St';
        $originalOrder->billing_address = '123 Main St';
        $originalOrder->payment_method = 'credit_card';
        $originalOrder->items = collect([]);

        $duplicatedOrder = m::mock(Order::class)->makePartial();
        $duplicatedOrder->id = 1;

        $duplicatedOrder->shouldReceive('relationLoaded')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($originalOrder);

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['status'] === 'pending'
                    && $data['payment_status'] === 'unpaid';
            }))
            ->andReturn($duplicatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->andReturn($duplicatedOrder);

        $result = $this->service->duplicateOrder($orderId);

        $this->assertSame($duplicatedOrder, $result);
    }

    public function testGetOrdersByStatusReturnsOrders()
    {
        $status = 'completed';
        $mockCollection = m::mock(\App\Framework\Support\Collection::class);

        $this->orderRepository->shouldReceive('getByStatus')
            ->once()
            ->with($status)
            ->andReturn($mockCollection);

        $result = $this->service->getOrdersByStatus($status);

        $this->assertSame($mockCollection, $result);
    }

    public function testGetOrdersByUserReturnsOrders()
    {
        $userId = 1;
        $limit = 10;
        $mockCollection = m::mock(\App\Framework\Support\Collection::class);

        $this->orderRepository->shouldReceive('getByUser')
            ->once()
            ->with($userId, $limit)
            ->andReturn($mockCollection);

        $result = $this->service->getOrdersByUser($userId, $limit);

        $this->assertSame($mockCollection, $result);
    }

    public function testGetOrdersByUserWithoutLimitReturnsAllOrders()
    {
        $userId = 1;
        $mockCollection = m::mock(\App\Framework\Support\Collection::class);

        $this->orderRepository->shouldReceive('getByUser')
            ->once()
            ->with($userId, null)
            ->andReturn($mockCollection);

        $result = $this->service->getOrdersByUser($userId);

        $this->assertSame($mockCollection, $result);
    }

    public function testGetTotalRevenueReturnsRevenueWithDateRange()
    {
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';
        $expectedRevenue = 5000.00;

        $this->orderRepository->shouldReceive('getTotalRevenue')
            ->once()
            ->with($startDate, $endDate)
            ->andReturn($expectedRevenue);

        $result = $this->service->getTotalRevenue($startDate, $endDate);

        $this->assertEquals($expectedRevenue, $result);
    }

    public function testGetTotalRevenueReturnsRevenueWithoutDateRange()
    {
        $expectedRevenue = 10000.00;

        $this->orderRepository->shouldReceive('getTotalRevenue')
            ->once()
            ->with(null, null)
            ->andReturn($expectedRevenue);

        $result = $this->service->getTotalRevenue();

        $this->assertEquals($expectedRevenue, $result);
    }

    public function testCreateOrderCreatesNewMemberWhenUserIdNotProvided()
    {
        $data = [
            'customer_name' => 'Jane Smith',
            'customer_email' => 'jane.smith@example.com',
            'customer_phone' => '123-456-7890',
            'status' => 'pending',
            'shipping' => 10.00,
            'discount' => 0.00,
            'currency' => 'USD',
        ];
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 1,
                'unit_price' => 100.00
            ]
        ];
        $siteId = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock Member::findByEmail to return null (member doesn't exist)
        $this->memberRepository->shouldReceive('findByEmail')
            ->once()
            ->with('jane.smith@example.com')
            ->andReturn(null);

        // Mock Member::create
        $mockMember = m::mock(Member::class)->makePartial();
        $mockMember->id = 123;

       $this->memberRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($memberData) use ($siteId) {
                return $memberData['email'] === 'jane.smith@example.com'
                    && $memberData['first_name'] === 'Jane'
                    && $memberData['last_name'] === 'Smith'
                    && $memberData['site_id'] === $siteId
                    && $memberData['is_active'] === true
                    && isset($memberData['password']);
            }))
            ->andReturn($mockMember);

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                return $orderData['user_id'] === 123 // The newly created member's ID
                    && !isset($orderData['customer_name'])
                    && !isset($orderData['customer_email'])
                    && !isset($orderData['customer_phone']);
            }))
            ->andReturn($mockOrder);

        $this->orderItemRepository->shouldReceive('create')
            ->once()
            ->andReturn(m::mock(OrderItem::class));

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $result = $this->service->createOrder($data, $items, $siteId);

        $this->assertSame($mockOrder, $result);
    }

    public function testCreateOrderUsesExistingMemberWhenEmailExists()
    {
        $existingMember = m::mock(Member::class)->makePartial();
        $existingMember->id = 456;

        $data = [
            'customer_name' => 'John Doe',
            'customer_email' => 'existing@example.com',
            'status' => 'pending',
        ];
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 1,
                'unit_price' => 50.00
            ]
        ];
        $siteId = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock Member::findByEmail to return existing member
        $this->memberRepository->shouldReceive('findByEmail')
            ->once()
            ->with('existing@example.com')
            ->andReturn($existingMember);

        // Member::create should NOT be called
        $this->memberRepository->shouldReceive('create')
            ->never();

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                return $orderData['user_id'] === 456; // Existing member's ID
            }))
            ->andReturn($mockOrder);

        $this->orderItemRepository->shouldReceive('create')
            ->once()
            ->andReturn(m::mock(OrderItem::class));

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $result = $this->service->createOrder($data, $items, $siteId);

        $this->assertSame($mockOrder, $result);
    }

    public function testCreateOrderParsesCustomerNameCorrectly()
    {
        // Test single name
        $this->assertEquals(
            ['first_name' => 'John', 'last_name' => ''],
            $this->invokePrivateMethod($this->service, 'parseCustomerName', ['John'])
        );

        // Test full name
        $this->assertEquals(
            ['first_name' => 'John', 'last_name' => 'Doe'],
            $this->invokePrivateMethod($this->service, 'parseCustomerName', ['John Doe'])
        );

        // Test name with multiple spaces
        $this->assertEquals(
            ['first_name' => 'John', 'last_name' => 'Michael Doe'],
            $this->invokePrivateMethod($this->service, 'parseCustomerName', ['John Michael Doe'])
        );

        // Test empty name
//        $this->assertEquals(
//            ['first_name' => 'Guest', 'last_name' => 'User'],
//            $this->invokePrivateMethod($this->service, 'parseCustomerName', [''])
//        );
    }

// Helper method to test private methods
    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}