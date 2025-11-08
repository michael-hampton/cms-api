<?php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Models\Address;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\AddressRepository;
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
    private $addressRepository;

    protected function setUp(): void
    {
        parent::setUp(); // Call parent setup if it exists
        // Use Mockery::mock() instead of $this->createMock()
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->addressRepository = m::mock(AddressRepository::class);
        $this->memberRepository = m::mock(MemberRepository::class);
        $this->orderItemRepository = m::mock(OrderItemRepository::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new OrderService(
            $this->orderRepository,
            $this->orderItemRepository,
            $this->memberRepository,
            $this->addressRepository,
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
            ->with(['items', 'user', 'item.product'])
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

        // ADD: Mock member lookup since user_id is provided
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

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

        // ADD: Mock member lookup
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

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

        // ADD: Mock member lookup
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
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

        // ADD: Mock member lookup
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->andReturn($mockOrder);

        $this->orderItemRepository->shouldReceive('create')
            ->times(3)
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
        $originalOrder->shipping_address_id = null; // ADD THIS
        $originalOrder->billing_address_id = null;  // ADD THIS
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

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with($originalOrder->user_id)
            ->andReturn(m::mock(Member::class));

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

    public function testBulkUpdateStatusSuccessfully(): void
    {
        $order1 = m::mock(Order::class)->makePartial();
        $order1->status = 'pending';
        $order1->completed_at = null;

        $order2 = m::mock(Order::class)->makePartial();
        $order2->status = 'pending';
        $order2->completed_at = null;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->orderRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($order1);

        $this->orderRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($order2);

        $this->orderRepository->shouldReceive('update')
            ->twice()
            ->andReturn($order1, $order2);

        $result = $this->service->bulkUpdateStatus([1, 2], 'shipped');

        $this->assertCount(2, $result['updated']);
        $this->assertCount(0, $result['failed']);
        $this->assertEquals(2, $result['total']);
    }

    public function testBulkUpdateStatusHandlesNotFound(): void
    {
        $order1 = m::mock(Order::class)->makePartial();
        $order1->status = 'pending';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->orderRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($order1);

        $this->orderRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->andReturn($order1);

        $result = $this->service->bulkUpdateStatus([1, 999], 'shipped');

        $this->assertCount(1, $result['updated']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals('Order not found', $result['failed'][0]['reason']);
    }

    public function testCreateOrderWithShippingAddressId()
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $address = m::mock(Address::class)->makePartial();
        $address->id = 10;
        $address->member_id = 1;

        $data = [
            'user_id' => 1,
            'shipping_address_id' => 10,
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

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($address);

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['shipping_address_id'] === 10
                    && !isset($data['shipping_address']);
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

    public function testCreateOrderThrowsExceptionForInvalidAddressId()
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $address = m::mock(Address::class)->makePartial();
        $address->id = 10;
        $address->member_id = 2; // Different member

        $data = [
            'user_id' => 1,
            'shipping_address_id' => 10,
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($address);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid shipping address');

        $this->service->createOrder($data, $items, $siteId);
    }

    public function testCreateOrderCreatesNewAddressFromData()
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $newAddress = m::mock(Address::class)->makePartial();
        $newAddress->id = 20;

        $data = [
            'user_id' => 1,
            'shipping_address' => [
                'address_line_1' => '123 Main St',
                'city' => 'City',
                'postcode' => '12345',
                'country' => 'US'
            ],
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

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

        $this->addressRepository->shouldReceive('createAddressForMember')
            ->once()
            ->with(1, m::on(function ($addressData) {
                return $addressData['address_line_1'] === '123 Main St'
                    && $addressData['type'] === 'shipping'
                    && $addressData['label'] === 'Order Address';
            }))
            ->andReturn($newAddress);

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['shipping_address_id'] === 20
                    && !isset($data['shipping_address']);
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

    public function testUpdateOrderWithShippingAddressId()
    {
        $orderId = 1;
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $address = m::mock(Address::class)->makePartial();
        $address->id = 10;
        $address->member_id = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'pending';
        $mockOrder->user_id = 1;
        $mockOrder->completed_at = null;

        $updatedOrder = m::mock(Order::class)->makePartial();
        $updatedOrder->id = $orderId;

        $data = [
            'shipping_address_id' => 10,
            'status' => 'processing'
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($address);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($updateData) {
                return $updateData['shipping_address_id'] === 10
                    && $updateData['shipping_address'] === null
                    && $updateData['status'] === 'processing';
            }))
            ->andReturn($updatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($updatedOrder);

        $result = $this->service->updateOrder($orderId, $data);

        $this->assertSame($updatedOrder, $result);
    }

    public function testUpdateOrderWithNewAddressData()
    {
        $orderId = 1;
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $newAddress = m::mock(Address::class)->makePartial();
        $newAddress->id = 20;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'pending';
        $mockOrder->user_id = 1;

        $updatedOrder = m::mock(Order::class)->makePartial();

        $data = [
            'shipping_address' => [
                'address_line_1' => '999 New St',
                'city' => 'New City',
                'postcode' => '99999',
                'country' => 'US'
            ]
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

        $this->addressRepository->shouldReceive('createAddressForMember')
            ->once()
            ->with(1, m::on(function ($addressData) {
                return $addressData['address_line_1'] === '999 New St'
                    && $addressData['type'] === 'shipping'
                    && $addressData['label'] === 'Order Address (Updated)';
            }))
            ->andReturn($newAddress);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($updateData) {
                return $updateData['shipping_address_id'] === 20
                    && !isset($updateData['shipping_address']);
            }))
            ->andReturn($updatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($updatedOrder);

        $result = $this->service->updateOrder($orderId, $data);

        $this->assertSame($updatedOrder, $result);
    }

    public function testUpdateOrderKeepsJsonAddressForGuestOrder()
    {
        $orderId = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'pending';
        $mockOrder->user_id = null; // Guest order

        $updatedOrder = m::mock(Order::class)->makePartial();

        $data = [
            'shipping_address' => [
                'address_line_1' => '123 Guest St',
                'city' => 'City',
                'postcode' => '12345',
                'country' => 'US'
            ]
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        // Should NOT create address or look up member
        $this->memberRepository->shouldReceive('find')->never();
        $this->addressRepository->shouldReceive('createAddressForMember')->never();

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($updateData) {
                return isset($updateData['shipping_address'])
                    && is_array($updateData['shipping_address'])
                    && $updateData['shipping_address']['address_line_1'] === '123 Guest St'
                    && !isset($updateData['shipping_address_id']);
            }))
            ->andReturn($updatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($updatedOrder);

        $result = $this->service->updateOrder($orderId, $data);

        $this->assertSame($updatedOrder, $result);
    }

    public function testUpdateOrderWithBothShippingAndBillingAddresses()
    {
        $orderId = 1;
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $shippingAddress = m::mock(Address::class)->makePartial();
        $shippingAddress->id = 10;
        $shippingAddress->member_id = 1;

        $billingAddress = m::mock(Address::class)->makePartial();
        $billingAddress->id = 11;
        $billingAddress->member_id = 1;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'pending';
        $mockOrder->user_id = 1;

        $updatedOrder = m::mock(Order::class)->makePartial();

        $data = [
            'shipping_address_id' => 10,
            'billing_address_id' => 11
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

        $this->addressRepository->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn($shippingAddress);

        $this->addressRepository->shouldReceive('find')
            ->with(11)
            ->once()
            ->andReturn($billingAddress);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($updateData) {
                return $updateData['shipping_address_id'] === 10
                    && $updateData['billing_address_id'] === 11
                    && $updateData['shipping_address'] === null
                    && $updateData['billing_address'] === null;
            }))
            ->andReturn($updatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($updatedOrder);

        $result = $this->service->updateOrder($orderId, $data);

        $this->assertSame($updatedOrder, $result);
    }


    public function testUpdateOrderThrowsExceptionForInvalidAddressId()
    {
        $orderId = 1;
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $address = m::mock(Address::class)->makePartial();
        $address->id = 10;
        $address->member_id = 2; // Different member

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->user_id = 1;
        $mockOrder->status = 'pending';

        $data = [
            'shipping_address_id' => 10
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($address);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid shipping address');

        $this->service->updateOrder($orderId, $data);
    }

    public function testDuplicateOrderWithLinkedAddresses()
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
        $originalOrder->payment_method = 'credit_card';
        $originalOrder->shipping_address_id = 20;
        $originalOrder->billing_address_id = 21;
        $originalOrder->shipping_address = null;
        $originalOrder->billing_address = null;
        $originalOrder->items = collect([]);

        $duplicatedOrder = m::mock(Order::class)->makePartial();
        $duplicatedOrder->id = 2;

        $this->databaseMock->shouldReceive('transaction')
            ->twice() // Once for duplicateOrder, once for createOrder
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($originalOrder);

        // Mock member lookup in createOrder
        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($member);

        // Mock address validation in createOrder
        $shippingAddress = m::mock(Address::class)->makePartial();
        $shippingAddress->id = 20;
        $shippingAddress->member_id = 10;

        $billingAddress = m::mock(Address::class)->makePartial();
        $billingAddress->id = 21;
        $billingAddress->member_id = 10;

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturn($shippingAddress);

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(21)
            ->andReturn($billingAddress);

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['status'] === 'pending'
                    && $data['payment_status'] === 'unpaid'
                    && $data['shipping_address_id'] === 20
                    && $data['billing_address_id'] === 21
                    && !isset($data['shipping_address'])
                    && !isset($data['billing_address']);
            }))
            ->andReturn($duplicatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(2)
            ->andReturn($duplicatedOrder);

        $result = $this->service->duplicateOrder($orderId);

        $this->assertSame($duplicatedOrder, $result);
    }

    public function testDuplicateOrderWithMixedAddresses()
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
        $originalOrder->payment_method = 'credit_card';
        $originalOrder->shipping_address_id = 20; // Linked
        $originalOrder->billing_address_id = null;
        $originalOrder->shipping_address = null;
        $originalOrder->billing_address = [ // JSON
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US'
        ];
        $originalOrder->items = collect([]);

        $duplicatedOrder = m::mock(Order::class)->makePartial();
        $duplicatedOrder->id = 2;

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($originalOrder);

        // Mock member lookup
        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($member);

        // Mock shipping address validation (linked)
        $shippingAddress = m::mock(Address::class)->makePartial();
        $shippingAddress->id = 20;
        $shippingAddress->member_id = 10;

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturn($shippingAddress);

        $this->addressRepository->shouldReceive('createAddressForMember')
            ->once()
            ->with(10, m::on(function ($addressData) {
                return $addressData['address_line_1'] === '456 Oak Ave'
                    && $addressData['type'] === 'billing'
                    && $addressData['label'] === 'Order Billing Address';
            }))
            ->andReturn($shippingAddress);

        // No address validation needed for billing (it's JSON)

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['billing_address_id'] === 20;
            }))
            ->andReturn($duplicatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(2)
            ->andReturn($duplicatedOrder);

        $result = $this->service->duplicateOrder($orderId);

        $this->assertSame($duplicatedOrder, $result);
    }

    public function testDuplicateOrderWithJsonAddresses()
    {
        $orderId = 1;

        $originalOrder = m::mock(Order::class)->makePartial();
        $originalOrder->user_id = null; // Guest order
        $originalOrder->status = 'completed';
        $originalOrder->subtotal = 100.00;
        $originalOrder->tax = 10.00;
        $originalOrder->shipping = 5.00;
        $originalOrder->discount = 0.00;
        $originalOrder->total = 115.00;
        $originalOrder->currency = 'USD';
        $originalOrder->site_id = 1;
        $originalOrder->payment_method = 'credit_card';
        $originalOrder->shipping_address_id = null;
        $originalOrder->billing_address_id = null;
        $originalOrder->shipping_address = [
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US'
        ];
        $originalOrder->billing_address = [
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US'
        ];
        $originalOrder->items = collect([]);

        $duplicatedOrder = m::mock(Order::class)->makePartial();
        $duplicatedOrder->id = 2;

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
                    && $data['payment_status'] === 'unpaid'
                    && !isset($data['shipping_address_id'])
                    && !isset($data['billing_address_id'])
                    && is_array($data['shipping_address'])
                    && $data['shipping_address']['address_line_1'] === '123 Main St'
                    && is_array($data['billing_address'])
                    && $data['billing_address']['address_line_1'] === '456 Oak Ave';
            }))
            ->andReturn($duplicatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(2)
            ->andReturn($duplicatedOrder);

        $result = $this->service->duplicateOrder($orderId);

        $this->assertSame($duplicatedOrder, $result);
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