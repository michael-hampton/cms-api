<?php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Framework\Mail\MailManager;
use App\Framework\Mail\PendingMail;
use App\Mail\RefundConfirmation;
use App\Models\Member;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\RefundRepository;
use App\Services\OrderHistoryService;
use App\Services\RefundService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class RefundServiceTest extends FunctionalTestCase
{
    private $refundRepository;
    private $orderRepository;
    private $productRepository;
    private $historyService;
    private $mailManager;
    private $databaseMock;
    private RefundService $service;

    public function testCreateRefundCreatesFullRefund(): void
    {
        $orderId = 1;
        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = $orderId;
        $mockOrder->total = 200.00;
        $mockOrder->status = 'completed';
        $mockOrder->payment_status = 'paid';
        $mockOrder->site_id = 1;
        $mockOrder->user = m::mock(Member::class)->makePartial();
        $mockOrder->user->email = 'test@example.com';

        $mockRefund = m::mock(Refund::class)->makePartial();
        $mockRefund->id = 1;
        $mockRefund->status = 'pending';
        $mockRefund->order_id = $orderId;

        $data = [
            'order_id' => $orderId,
            'refund_type' => 'full',
            'refund_amount' => 200.00,
            'reason' => 'customer_request',
            'notify_customer' => true,
            'restock_items' => false
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->twice()
            ->with($orderId)
            ->andReturn($mockOrder);

        $mockOrder->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->refundRepository->shouldReceive('getTotalRefundedAmount')
            ->twice()
            ->with($orderId)
            ->andReturn(0.0, 200.00);

        $this->refundRepository->shouldReceive('create')
            ->once()
            ->andReturn($mockRefund);

        $this->refundRepository->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, 'processed', null)
            ->andReturn(true);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with($orderId, m::on(function ($data) {
                return $data['status'] === 'refunded'
                    && $data['payment_status'] === 'refunded';
            }));

        $this->historyService->shouldReceive('logRefundCreated')
            ->once();

        $pendingMail = m::mock(PendingMail::class);
        $this->mailManager->shouldReceive('to')
            ->once()
            ->with('test@example.com')
            ->andReturn($pendingMail);

        $pendingMail->shouldReceive('send')
            ->once()
            ->with(m::type(RefundConfirmation::class));

        $this->refundRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($mockRefund);

        $result = $this->service->createRefund($data);

        $this->assertSame($mockRefund, $result);
    }

    public function testCreateRefundCreatesPartialRefundWithItems(): void
    {
        $orderId = 1;
        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = $orderId;
        $mockOrder->total = 200.00;
        $mockOrder->site_id = 1;
        $mockOrder->user = null;
        $mockOrder->status = 'completed';
        $mockOrder->payment_status = 'paid';

        $mockRefund = m::mock(Refund::class)->makePartial();
        $mockRefund->id = 1;
        $mockRefund->status = 'pending';
        $mockRefund->order_id = $orderId;

        $mockProduct = m::mock(Product::class)->makePartial();
        $mockProduct->id = 1;
        $mockProduct->stock_quantity = 10;

        $data = [
            'order_id' => $orderId,
            'refund_type' => 'partial',
            'refund_amount' => 100.00,
            'reason' => 'damaged_item',
            'items' => [
                [
                    'id' => 1,
                    'product_id' => 1,
                    'product_name' => 'Test Product',
                    'quantity' => 2,
                    'refund_quantity' => 1,
                    'price' => 100.00,
                    'refund_amount' => 100.00
                ]
            ],
            'notify_customer' => false,
            'restock_items' => true
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $mockOrder->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->refundRepository->shouldReceive('getTotalRefundedAmount')
            ->twice()
            ->with($orderId)
            ->andReturn(0.0, 50);

        $this->refundRepository->shouldReceive('create')
            ->once()
            ->andReturn($mockRefund);

        $this->refundRepository->shouldReceive('createRefundItem')
            ->once();

        $mockRefundItem = m::mock(\App\Models\RefundItem::class)->makePartial();
        $mockRefundItem->product_id = 1;
        $mockRefundItem->refund_quantity = 1;

        $mockCollection = m::mock(\App\Framework\Support\Collection::class);
        $mockCollection->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator([$mockRefundItem]));

        $this->refundRepository->shouldReceive('getRefundItems')
            ->once()
            ->with(1)
            ->andReturn($mockCollection);

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($mockProduct);

        $this->productRepository->shouldReceive('update')
            ->once()
            ->with(1, ['stock_quantity' => 11]);

        $this->refundRepository->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, 'processed', null)
            ->andReturn(true);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(1, m::on(function ($arg) {
                return is_array($arg)
                    && isset($arg['status'])
                    && $arg['status'] === 'partially_refunded';
            }));;

        $this->historyService->shouldReceive('logRefundCreated')
            ->once();

        $this->refundRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($mockRefund);

        $result = $this->service->createRefund($data);

        $this->assertSame($mockRefund, $result);
    }

    public function testCreateRefundThrowsExceptionForNonExistentOrder(): void
    {
        $data = [
            'order_id' => 999,
            'refund_type' => 'full',
            'refund_amount' => 100.00,
            'reason' => 'customer_request'
        ];

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

        $this->service->createRefund($data);
    }

    public function testCreateRefundThrowsExceptionForUnrefundableOrder(): void
    {
        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $mockOrder->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(false);

        $data = [
            'order_id' => 1,
            'refund_type' => 'full',
            'refund_amount' => 100.00,
            'reason' => 'customer_request'
        ];

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
        $this->expectExceptionMessage('Order cannot be refunded');

        $this->service->createRefund($data);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundRepository = m::mock(RefundRepository::class);
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->productRepository = m::mock(ProductRepository::class);
        $this->historyService = m::mock(OrderHistoryService::class);
        $this->mailManager = m::mock(MailManager::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new RefundService(
            $this->refundRepository,
            $this->orderRepository,
            $this->productRepository,
            $this->historyService,
            $this->mailManager,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

//    public function testCreateRefundThrowsExceptionWhenAmountExceedsRemaining(): void
//    {
//        $mockOrder = m::mock(Order::class)->makePartial();
//        $mockOrder->id = 1;
//        $mockOrder->total = 200.00;
//
//        $mockOrder->shouldReceive('canBeRefunded')
//            ->once()
//            ->andReturn(true);
//
//        $data = [
//            'order_id' => 1,
//            'refund_type' => 'partial',
//            'refund_amount' => 100.00,
//            'reason' => 'customer_request'
//        ];
//
//        $this->databaseMock->shouldReceive('transaction')
//            ->once()
//            ->andReturnUsing(function ($callback) {
//                return $callback();
//            });
//
//        $this->orderRepository->shouldReceive('find')
//            ->once()
//            ->with(1)
//            ->andReturn($mockOrder);
//
//        $this->refundRepository->shouldReceive
}
