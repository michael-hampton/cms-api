<?php

namespace App\Tests\Unit\Services\Cms;

use App\Framework\Database\Database;
use App\Framework\Mail\MailManager;
use App\Framework\Mail\PendingMail;
use App\Mail\OrderConfirmation;
use App\Models\Address;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderItem;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Members\OrderItemRepository;
use App\Repositories\Members\OrderRepository;
use App\Services\Members\OrderCalculationService;
use App\Services\Members\OrderHistoryService;
use App\Services\Members\OrderService;
use App\Services\Members\PaymentService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery as m;

// Import Mockery with a simple alias

class OrderServiceTest extends FunctionalTestCase
{
    use HasSiteHistory;

    private $orderRepository;
    private $orderItemRepository;
    private $memberRepository;
    private $databaseMock;
    private OrderService $service;
    private $addressRepository;
    private $orderCalculationService;
    private $paymentService;
    private $historyService;

    private $mailManager;

    protected function setUp(): void
    {
        parent::setUp(); // Call parent setup if it exists
        // Use Mockery::mock() instead of $this->createMock()
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->addressRepository = m::mock(AddressRepository::class);
        $this->orderCalculationService = m::mock(OrderCalculationService::class);
        $this->memberRepository = m::mock(MemberRepository::class);
        $this->orderItemRepository = m::mock(OrderItemRepository::class);
        $this->databaseMock = m::mock(Database::class);
        $this->historyService = m::mock(OrderHistoryService::class); // ADD THIS
        $this->mailManager = m::mock(MailManager::class);
        $this->paymentService = m::mock(PaymentService::class);

        $this->service = new OrderService(
            $this->orderRepository,
            $this->orderItemRepository,
            $this->memberRepository,
            $this->addressRepository,
            $this->orderCalculationService,
            $this->historyService,
            $this->mailManager,
            $this->paymentService,
            $this->databaseMock,
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

        $member = m::mock(Member::class)->makePartial();
        $member->email = 'michaelhamptondesign@yahoo.com';

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->user = $member;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->historyService->shouldReceive('logCreated')
            ->once()
            ->with(1, m::any(), 1)
            ->andReturn(m::mock(OrderHistory::class));

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($items, m::any())
            ->andReturn([
                'subtotal' => 100.00,
                'tax' => 10.00,
                'shipping' => 0.00,
                'discount' => 0.00,
                'total' => 110.00
            ]);

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

        $this->setMailExpectations();

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

        $member = m::mock(Member::class)->makePartial();
        $member->email = 'michaelhamptondesign@yahoo.com';

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->user = $member;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->historyService->shouldReceive('logCreated')
            ->once()
            ->with(1, m::any(), 1)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->setMailExpectations();

        $this->orderItemRepository->shouldReceive('create')
            ->once()
            ->andReturn(m::mock(OrderItem::class));

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($items, m::any())
            ->andReturn([
                'subtotal' => 100.00,
                'tax' => 10.00,
                'shipping' => 0.00,
                'discount' => 0.00,
                'total' => 110.00
            ]);

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

        $member = m::mock(Member::class)->makePartial();
        $member->email = 'michaelhamptondesign@yahoo.com';

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->user = $member;

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

        $this->historyService->shouldReceive('logCreated')
            ->once()
            ->with(1, m::any(), 1)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->setMailExpectations();

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($items, m::on(function ($orderData) {
                return $orderData['shipping'] == 10.00
                    && $orderData['discount'] == 5.00;
            }))
            ->andReturn([
                'subtotal' => 130.00,
                'tax' => 13.00,
                'shipping' => 10.00,
                'discount' => 5.00,
                'total' => 148.00
            ]);

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

        $member = m::mock(Member::class)->makePartial();
        $member->email = 'michaelhamptondesign@yahoo.com';

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->user = $member;

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

        $this->historyService->shouldReceive('logCreated')
            ->once()
            ->with(1, m::any(), 1)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->setMailExpectations();

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($items, m::any())
            ->andReturn([
                'subtotal' => 135.00,
                'tax' => 13.50,
                'shipping' => 0.00,
                'discount' => 0.00,
                'total' => 148.50
            ]);

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
        $mockOrder->status = 'processing';
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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

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
        $mockOrder->status = 'processing';
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

        $this->historyService->shouldReceive('logItemsUpdated')
            ->once()
            ->with($orderId, null)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($items, ['shipping' => 10, 'discount' => 0])
            ->andReturn([
                'subtotal' => 100.00,
                'tax' => 10.00,
                'shipping' => 10.00,
                'discount' => 0.00,
                'total' => 120.00
            ]);

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

        $this->historyService->shouldReceive('logItemsUpdated')
            ->once()
            ->with($orderId, null)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($items, ['shipping' => 10.00, 'discount' => 5.00])
            ->andReturn([
                'subtotal' => 130.00,
                'tax' => 8.00,
                'shipping' => 10.00,
                'discount' => 5.00,
                'total' => 143.00
            ]);

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
            ->atLeast()
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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

        $this->historyService->shouldReceive('logCancelled')
            ->once()
            ->with($orderId, null, $reason)
            ->andReturn(m::mock(OrderHistory::class));

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
            ->atLeast()
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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

        $this->historyService->shouldReceive('logCancelled')
            ->once()
            ->with($orderId, null, null)
            ->andReturn(m::mock(OrderHistory::class));

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
            ->atLeast()
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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

        $this->historyService->shouldReceive('logCancelled')
            ->once()
            ->with($orderId, null, $reason)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

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

        $member = m::mock(Member::class)->makePartial();
        $member->email = 'michaelhamptondesign@yahoo.com';

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->user = $member;

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

        $this->setMailExpectations();

        $this->historyService->shouldReceive('logCreated')
            ->once()
            ->with(1, m::any(), 123)
            ->andReturn(m::mock(OrderHistory::class));

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($items, m::any())
            ->andReturn([
                'subtotal' => 100.00,
                'tax' => 10.00,
                'shipping' => 10.00,
                'discount' => 0.00,
                'total' => 120.00
            ]);

        $result = $this->service->createOrder($data, $items, $siteId);

        $this->assertSame($mockOrder, $result);
    }

    public function testCreateOrderUsesExistingMemberWhenEmailExists()
    {
        $existingMember = m::mock(Member::class)->makePartial();
        $existingMember->id = 456;
        $existingMember->email = 'michaelhamptondesign@yahoo.com';

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
        $mockOrder->user = $existingMember;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->historyService->shouldReceive('logCreated')
            ->once()
            ->with(1, m::any(), 456)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->setMailExpectations();

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($items, m::any())
            ->andReturn([
                'subtotal' => 50.00,
                'tax' => 5.00,
                'shipping' => 0.00,
                'discount' => 0.00,
                'total' => 55.00
            ]);

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


    public function testCreateOrderWithShippingAddressId()
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'michaelhamptondesign@yahoo.com';

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
        $mockOrder->user = $member;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($member);

        $this->historyService->shouldReceive('logCreated')
            ->once()
            ->with(1, m::any(), 1)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->setMailExpectations();

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($items, m::any())
            ->andReturn([
                'subtotal' => 100.00,
                'tax' => 10.00,
                'shipping' => 0.00,
                'discount' => 0.00,
                'total' => 110.00
            ]);

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
        $member->email = 'michaelhamptondesign@yahoo.com';

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
        $mockOrder->user = $member;

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
            }), $this->siteId)
            ->andReturn($newAddress);

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $this->historyService->shouldReceive('logCreated')
            ->once()
            ->with(1, m::any(), 1)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->setMailExpectations();

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with($items, m::any())
            ->andReturn([
                'subtotal' => 100.00,
                'tax' => 10.00,
                'shipping' => 0.00,
                'discount' => 0.00,
                'total' => 110.00
            ]);

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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

        $this->addressRepository->shouldReceive('createAddressForMember')
            ->once()
            ->with(1, m::on(function ($addressData) {
                return $addressData['address_line_1'] === '999 New St'
                    && $addressData['type'] === 'shipping'
                    && $addressData['label'] === 'Order Address (Updated)';
            }), $this->siteId)
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

        $result = $this->service->updateOrder($orderId, $data, $this->siteId);

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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

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

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->with($orderId, m::any(), m::any(), null)
            ->andReturn(m::mock(OrderHistory::class));

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

    public function testUpdateOrderWithInvalidTransitionThrowsException(): void
    {
        $orderId = 1;
        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'completed';

        $mockOrder->shouldReceive('canTransitionTo')
            ->once()
            ->with('pending')
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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot transition from completed to pending');

        $this->service->updateOrder($orderId, ['status' => 'pending']);
    }

    public function testUpdateOrderAllowsSameStatus(): void
    {
        $orderId = 1;
        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->status = 'pending';
        $mockOrder->user_id = 1;

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

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(m::mock(Member::class));

        // Should NOT call canTransitionTo when status is the same
        $mockOrder->shouldReceive('canTransitionTo')->never();

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->andReturn($updatedOrder);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->andReturn($updatedOrder);

        $this->historyService->shouldReceive('logUpdated')
            ->once()
            ->andReturn(m::mock(OrderHistory::class));

        $result = $this->service->updateOrder($orderId, ['status' => 'pending']);

        $this->assertSame($updatedOrder, $result);
    }

    public function setMailExpectations()
    {
        $pendingMail = m::mock(PendingMail::class)->makePartial();

        $this->mailManager->shouldReceive('to')
            ->once()
            ->with('michaelhamptondesign@yahoo.com')
            ->andReturn($pendingMail);

        $pendingMail->shouldReceive('send')
            ->once()
            ->with(m::type(OrderConfirmation::class));
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