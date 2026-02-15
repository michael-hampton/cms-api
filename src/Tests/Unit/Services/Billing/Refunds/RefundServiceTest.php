<?php

namespace App\Tests\Unit\Services\Billing\Refunds;

use App\Enums\Refunds\RefundStatus;
use App\Enums\Refunds\RefundType;
use App\Events\Refunds\RefundCancelled;
use App\Events\Refunds\RefundCreated;
use App\Events\Refunds\RefundProcessed;
use App\Exceptions\Orders\OrderNotFoundException;
use App\Exceptions\Orders\OrderNotRefundableException;
use App\Exceptions\Orders\RefundAlreadyProcessedException;
use App\Exceptions\Orders\RefundAmountExceedsRemainingException;
use App\Exceptions\Orders\RefundNotCancellableException;
use App\Exceptions\Orders\RefundNotFoundException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Order;
use App\Models\Refund;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\RefundRepository;
use App\Services\Billing\Refund\OrderStatusUpdater;
use App\Services\Billing\Refund\RefundAmountCalculator;
use App\Services\Billing\Refund\RefundAmountValidator;
use App\Services\Billing\Refund\RefundItemRestockHandler;
use App\Services\Billing\Refund\RefundService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class RefundServiceTest extends FunctionalTestCase
{
    private RefundRepository $refundRepository;
    private OrderRepository $orderRepository;
    private RefundAmountCalculator $amountCalculator;
    private RefundAmountValidator $amountValidator;
    private RefundItemRestockHandler $restockHandler;
    private OrderStatusUpdater $orderStatusUpdater;
    private EventDispatcher $eventDispatcher;
    private RefundService $service;
    private Database $databaseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundRepository = m::mock(RefundRepository::class);
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->amountCalculator = m::mock(RefundAmountCalculator::class);
        $this->amountValidator = m::mock(RefundAmountValidator::class);
        $this->restockHandler = m::mock(RefundItemRestockHandler::class);
        $this->orderStatusUpdater = m::mock(OrderStatusUpdater::class);
        $this->eventDispatcher = m::mock(EventDispatcher::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new RefundService(
            $this->refundRepository,
            $this->orderRepository,
            $this->amountCalculator,
            $this->amountValidator,
            $this->restockHandler,
            $this->orderStatusUpdater,
            $this->eventDispatcher,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testCreateRefundCreatesFullRefund(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $order = $this->createMockOrder(1, 200.00);
        $order->user = m::mock(\App\Models\Member::class)->makePartial();
        $order->user->email = 'test@example.com';

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($order);

        $order->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once()
            ->with($order, 200.00);

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['order_id'] === 1
                    && $data['refund_amount'] === 200.00
                    && $data['refund_type'] === RefundType::FULL->value
                    && $data['reason'] === 'customer_request'
                    && $data['notify_customer'] === true
                    && $data['restock_items'] === false;
            }))
            ->andReturn($refund);

        $refund->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, RefundStatus::PROCESSED->value, null)
            ->andReturn(true);

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once()
            ->with($order);

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $data = [
            'order_id' => 1,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'notify_customer' => true,
            'restock_items' => false
        ];

        $result = $this->service->createRefund($data);

        $this->assertSame($refund, $result);
    }

    public function testCreateRefundWithExplicitAmount(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($order);

        $order->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once()
            ->with($order, 150.00);

        $pendingRefund = $this->createMockRefund(1, RefundStatus::PENDING->value);
        $processedRefund = $this->createMockRefund(1, RefundStatus::PROCESSED->value);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['order_id'] === 1
                    && $data['refund_amount'] === 150.00
                    && $data['refund_type'] === RefundType::PARTIAL->value
                    && $data['status'] === RefundStatus::PENDING->value;
            }))
            ->andReturn($pendingRefund);

        $pendingRefund->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, RefundStatus::PROCESSED->value, null)
            ->andReturn(true);

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once()
            ->with($order);

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($pendingRefund, $processedRefund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $data = [
            'order_id' => 1,
            'refund_type' => 'partial',
            'refund_amount' => 150.00,
            'reason' => 'customer_request',
            'notify_customer' => true,
            'restock_items' => false
        ];

        $result = $this->service->createRefund($data);

        $this->assertSame($processedRefund, $result);
    }

    public function testCreateRefundCreatesPartialRefundWithItems(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $order = $this->createMockOrder(1, 200.00);
        $order->user = null;

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($order);

        $order->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $items = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Test Product',
                'quantity' => 2,
                'refund_quantity' => 1,
                'unit_price' => 100.00,
                'refund_amount' => 100.00
            ]
        ];

        $this->amountCalculator
            ->shouldReceive('calculateFromItems')
            ->once()
            ->with($items)
            ->andReturn(100.00);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once()
            ->with($order, 100.00);

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['order_id'] === 1
                    && $data['refund_amount'] === 100.00
                    && $data['refund_type'] === RefundType::PARTIAL->value
                    && $data['reason'] === 'damaged_item';
            }))
            ->andReturn($refund);

        $this->refundRepository
            ->shouldReceive('createRefundItem')
            ->once()
            ->with(m::on(function ($itemData) {
                return $itemData['refund_id'] === 1
                    && $itemData['order_item_id'] === 1
                    && $itemData['product_id'] === 1
                    && $itemData['product_name'] === 'Test Product'
                    && $itemData['quantity'] === 2
                    && $itemData['refund_quantity'] === 1
                    && $itemData['unit_price'] === 100.00
                    && $itemData['refund_amount'] === 100.00;
            }));

        $this->restockHandler
            ->shouldReceive('restockItems')
            ->once()
            ->with(1);

        $refund->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, RefundStatus::PROCESSED->value, null)
            ->andReturn(true);

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once()
            ->with($order);

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->never()
            ->with(m::type(RefundCreated::class));

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $data = [
            'order_id' => 1,
            'refund_type' => 'partial',
            'reason' => 'damaged_item',
            'items' => $items,
            'notify_customer' => false,
            'restock_items' => true
        ];

        $result = $this->service->createRefund($data);

        $this->assertSame($refund, $result);
    }

    public function testCreateRefundThrowsExceptionForNonExistentOrder(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(OrderNotFoundException::class);
        $this->expectExceptionMessage('Order with ID 999 not found');

        $data = [
            'order_id' => 999,
            'refund_type' => 'full',
            'refund_amount' => 100.00,
            'reason' => 'customer_request'
        ];

        $this->service->createRefund($data);
    }

    public function testCreateRefundThrowsExceptionForUnrefundableOrder(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($order);

        $order->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(false);

        $this->expectException(OrderNotRefundableException::class);
        $this->expectExceptionMessage('Order 1 cannot be refunded');

        $data = [
            'order_id' => 1,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request'
        ];

        $this->service->createRefund($data);
    }

    public function testCreateRefundThrowsExceptionWhenAmountExceedsRemaining(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($order);

        $order->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once()
            ->with($order, 150.00)
            ->andThrow(RefundAmountExceedsRemainingException::create(150.00, 50.00));

        $this->expectException(RefundAmountExceedsRemainingException::class);

        $data = [
            'order_id' => 1,
            'refund_type' => 'partial',
            'refund_amount' => 150.00,
            'reason' => 'customer_request'
        ];

        $this->service->createRefund($data);
    }

    public function testGetRefundsByOrderReturnsRefunds(): void
    {
        $orderId = 1;
        $mockCollection = m::mock(\App\Framework\Support\Collection::class);

        $this->refundRepository->shouldReceive('findByOrderId')
            ->once()
            ->with($orderId)
            ->andReturn($mockCollection);

        $result = $this->service->getRefundsByOrder($orderId);

        $this->assertSame($mockCollection, $result);
    }

    public function testCancelRefundCancelsPendingRefund(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value);

        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($refund);

        $refund->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, RefundStatus::CANCELLED->value, 456)
            ->andReturn(true);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCancelled::class));

        $result = $this->service->cancelRefund(1, 456);

        $this->assertTrue($result);
    }

    public function testCancelRefundThrowsExceptionForNonPendingRefund(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $refund = $this->createMockRefund(1, RefundStatus::PROCESSED->value);

        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($refund);

        $refund->shouldReceive('isPending')
            ->once()
            ->andReturn(false);

        $this->expectException(RefundNotCancellableException::class);
        $this->expectExceptionMessage('Only pending refunds can be cancelled');

        $this->service->cancelRefund(1);
    }

    public function testGetRemainingRefundableAmountReturnsCorrectAmount(): void
    {
        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($order);

        $this->amountValidator
            ->shouldReceive('getRemainingAmount')
            ->once()
            ->with($order)
            ->andReturn(50.00);

        $result = $this->service->getRemainingRefundableAmount(1);

        $this->assertEquals(50.00, $result);
    }


    public function testGetRemainingRefundableAmountReturnsZeroForNonExistentOrder(): void
    {
        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->getRemainingRefundableAmount(999);

        $this->assertEquals(0.0, $result);
    }

    public function testProcessRefundProcessesPendingRefund(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value);

        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($refund);

        $refund->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, RefundStatus::PROCESSED->value, 123)
            ->andReturn(true);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $result = $this->service->processRefund(1, 123);

        $this->assertTrue($result);
    }

    public function testProcessRefundThrowsExceptionForNonExistentRefund(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(RefundNotFoundException::class);
        $this->expectExceptionMessage('Refund with ID 999 not found');

        $this->service->processRefund(999);
        $this->assertTrue(true);
    }


    public function testProcessRefundThrowsExceptionForAlreadyProcessedRefund(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $refund = $this->createMockRefund(1, RefundStatus::PROCESSED->value);

        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($refund);

        $refund->shouldReceive('isPending')
            ->once()
            ->andReturn(false);

        $this->expectException(RefundAlreadyProcessedException::class);
        $this->expectExceptionMessage('Refund 1 has already been processed');

        $this->service->processRefund(1);
    }

    public function testCreateRefundRestocksItems(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($order);

        $order->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once();

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refund);

        $this->restockHandler
            ->shouldReceive('restockItems')
            ->once()
            ->with(1);

        $refund->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once();

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once();

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->twice();

        $data = [
            'order_id' => 1,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'restock_items' => true
        ];

        $this->service->createRefund($data);
        $this->assertTrue(true);
    }

    public function testCreateRefundEmitsEventWhenNotifyCustomerIsTrue(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $order = $this->createMockOrder(1, 200.00);

        $this->setupBasicCreateRefundMocks($order);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::on(function ($event) {
                return $event instanceof RefundCreated;
            }));

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $data = [
            'order_id' => 1,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'notify_customer' => true,
            'restock_items' => false
        ];

        $this->service->createRefund($data);
        $this->assertTrue(true);
    }

    public function testCreateRefundSkipsEventWhenNotifyCustomerIsFalse(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $order = $this->createMockOrder(1, 200.00);

        $this->setupBasicCreateRefundMocks($order);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->never()
            ->with(m::type(RefundCreated::class));

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $data = [
            'order_id' => 1,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'notify_customer' => false,
            'restock_items' => false
        ];

        $this->service->createRefund($data);
        $this->assertTrue(true);
    }

    public function testCreateRefundSkipsRestockWhenDisabled(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($order);

        $order->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once();

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refund);

        $this->restockHandler
            ->shouldReceive('restockItems')
            ->never();

        $refund->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once();

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once();

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->twice();

        $data = [
            'order_id' => 1,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'restock_items' => false
        ];

        $this->service->createRefund($data);
        $this->assertTrue(true);
    }

    public function testCreateRefundCalculatesAmountFromItems(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($order);

        $order->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $items = [
            ['product_name' => 'Product 1', 'refund_amount' => 50.00],
            ['product_name' => 'Product 2', 'refund_amount' => 30.00]
        ];

        $this->amountCalculator
            ->shouldReceive('calculateFromItems')
            ->once()
            ->with($items)
            ->andReturn(80.00);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once()
            ->with($order, 80.00);

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['refund_amount'] === 80.00;
            }))
            ->andReturn($refund);

        $this->refundRepository
            ->shouldReceive('createRefundItem')
            ->twice();

        $refund->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once();

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once();

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->twice();

        $data = [
            'order_id' => 1,
            'refund_type' => 'partial',
            'reason' => 'damaged_item',
            'items' => $items,
            'notify_customer' => true,
            'restock_items' => false
        ];

        $result = $this->service->createRefund($data);
        $this->assertSame($refund, $result);
    }

    public function testGetRemainingRefundableAmountReturnsAmount(): void
    {
        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($order);

        $this->amountValidator
            ->shouldReceive('getRemainingAmount')
            ->once()
            ->with($order)
            ->andReturn(75.00);

        $result = $this->service->getRemainingRefundableAmount(1);

        $this->assertEquals(75.00, $result);
    }

    private function createMockOrder(int $id, float $total): Order
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = $id;
        $order->total = $total;
        $order->site_id = 1;
        return $order;
    }

    private function createMockRefund(int $id, string $status): Refund
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = $id;
        $refund->status = $status;
        return $refund;
    }

    private function setupBasicCreateRefundMocks(Order $order): void
    {
        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($order);

        $order->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once();

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refund);

        $refund->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once();

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once();

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($refund);
    }
}
