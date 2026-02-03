<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Framework\Database\Database;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\PaymentMethodRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Order\OrderCreationService;
use App\Services\Billing\Order\OrderPaymentService;
use App\Services\Billing\PaymentService;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class OrderPaymentServiceTest extends TestCase
{
    private $orderRepository;
    private $paymentService;
    private $database;
    private OrderPaymentService $service;
    private $paymentMethodRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = m::mock(OrderRepository::class);
        $this->paymentMethodRepository = m::mock(PaymentMethodRepository::class);

        $this->paymentRepository = m::mock(PaymentRepository::class);
        $this->paymentMethodRepository = m::mock(PaymentMethodRepository::class);
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->database = m::mock(Database::class);

        $this->paymentService = m::mock(
            PaymentService::class,
            [
                m::mock(PaymentRepository::class),
                $this->paymentMethodRepository,
                m::mock(OrderRepository::class),
                m::mock(SubscriptionRepository::class),
                m::mock(Database::class),
            ]
        );

        $this->database = m::mock(Database::class);

        $this->service = new OrderPaymentService(
            $this->orderRepository,
            $this->paymentService,
            $this->database
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ===================================================================
    // processPayment() with auto-complete Tests
    // ===================================================================

    public function testProcessPaymentWithAutoComplete(): void
    {
        $orderId = 1;
        $siteId = 1;
        $paymentData = [
            'payment_method' => 'cash',
            'amount' => 100.00
        ];

        $mockOrder = m::mock(Order::class);
        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;
        $mockPayment->status = 'pending';

        $mockCompletedPayment = m::mock(Payment::class)->makePartial();
        $mockCompletedPayment->id = 1;
        $mockCompletedPayment->status = 'completed';

        $mockPaymentMethod = m::mock(PaymentMethod::class);
        $mockPaymentMethod->shouldReceive('requiresProcessing')
            ->once()
            ->andReturn(false);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->paymentService->shouldReceive('createPayment')
            ->once()
            ->with($orderId, $paymentData, $siteId)
            ->andReturn($mockPayment);

        $this->paymentMethodRepository
            ->shouldReceive('findByCode')
            ->once()
            ->with('cash')
            ->andReturn($mockPaymentMethod);

        $this->paymentService->shouldReceive('completePayment')
            ->once()
            ->with(1)
            ->andReturn($mockCompletedPayment);

        $result = $this->service->processPayment($orderId, $paymentData, $siteId);

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertEquals('completed', $result->status);
    }

    public function testProcessPaymentAutoCompleteForCashPayment(): void
    {
        $orderId = 1;
        $siteId = 1;
        $paymentData = ['payment_method' => 'cash', 'amount' => 50.00];

        $mockOrder = m::mock(Order::class);
        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;

        $mockCompletedPayment = m::mock(Payment::class)->makePartial();
        $mockCompletedPayment->id = 1;
        $mockCompletedPayment->status = 'completed';

        $mockPaymentMethod = m::mock(PaymentMethod::class);
        $mockPaymentMethod->shouldReceive('requiresProcessing')->andReturn(false);

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->orderRepository->shouldReceive('find')->andReturn($mockOrder);
        $this->paymentService->shouldReceive('createPayment')->andReturn($mockPayment);
        $this->paymentMethodRepository
            ->shouldReceive('findByCode')
            ->andReturn($mockPaymentMethod);
        $this->paymentService->shouldReceive('completePayment')->andReturn($mockCompletedPayment);

        $result = $this->service->processPayment($orderId, $paymentData, $siteId);

        $this->assertEquals('completed', $result->status);
    }

    // ===================================================================
    // processPayment() with manual processing Tests
    // ===================================================================

    public function testProcessPaymentWithManualProcessing(): void
    {
        $orderId = 1;
        $siteId = 1;
        $paymentData = [
            'payment_method' => 'credit_card',
            'amount' => 100.00
        ];

        $mockOrder = m::mock(Order::class);
        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;
        $mockPayment->status = 'pending';

        $mockPaymentMethod = m::mock(PaymentMethod::class);
        $mockPaymentMethod->shouldReceive('requiresProcessing')
            ->once()
            ->andReturn(true); // Requires manual processing

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->paymentService->shouldReceive('createPayment')
            ->once()
            ->with($orderId, $paymentData, $siteId)
            ->andReturn($mockPayment);

        $this->paymentMethodRepository
            ->shouldReceive('findByCode')
            ->once()
            ->with('credit_card')
            ->andReturn($mockPaymentMethod);

        // Should NOT call completePayment
        $this->paymentService->shouldNotReceive('completePayment');

        $result = $this->service->processPayment($orderId, $paymentData, $siteId);

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertEquals('pending', $result->status);
    }

    public function testProcessPaymentForBankTransferStaysPending(): void
    {
        $orderId = 1;
        $siteId = 1;
        $paymentData = ['payment_method' => 'bank_transfer', 'amount' => 200.00];

        $mockOrder = m::mock(Order::class);
        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;
        $mockPayment->status = 'pending';

        $mockPaymentMethod = m::mock(PaymentMethod::class);
        $mockPaymentMethod->shouldReceive('requiresProcessing')->andReturn(true);

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->orderRepository->shouldReceive('find')->andReturn($mockOrder);
        $this->paymentService->shouldReceive('createPayment')->andReturn($mockPayment);
        $this->paymentMethodRepository
            ->shouldReceive('findByCode')
            ->andReturn($mockPaymentMethod);

        $result = $this->service->processPayment($orderId, $paymentData, $siteId);

        $this->assertEquals('pending', $result->status);
    }

    public function testProcessPaymentThrowsExceptionWhenOrderNotFound(): void
    {
        $orderId = 999;
        $siteId = 1;
        $paymentData = ['payment_method' => 'cash', 'amount' => 100.00];

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $this->service->processPayment($orderId, $paymentData, $siteId);
    }

    public function testProcessPaymentHandlesPaymentMethodNotFound(): void
    {
        $orderId = 1;
        $siteId = 1;
        $paymentData = ['payment_method' => 'unknown', 'amount' => 100.00];

        $mockOrder = m::mock(Order::class);
        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->orderRepository->shouldReceive('find')->andReturn($mockOrder);
        $this->paymentService->shouldReceive('createPayment')->andReturn($mockPayment);
        $this->paymentMethodRepository
            ->shouldReceive('findByCode')
            ->andReturn(null);

        // Should not throw, just return payment without completing
        $result = $this->service->processPayment($orderId, $paymentData, $siteId);

        $this->assertInstanceOf(Payment::class, $result);
    }

    // ===================================================================
    // createOrderWithPayment() Tests
    // ===================================================================

    public function testCreateOrderWithPayment(): void
    {
        $orderData = ['status' => 'pending'];
        $items = [['product_id' => 1, 'quantity' => 2]];
        $siteId = 1;
        $paymentData = [
            'payment_method' => 'credit_card',
            'amount' => 100.00
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;

        $mockOrderCreator = m::mock(OrderCreationService::class);
        $mockOrderCreator->shouldReceive('create')
            ->once()
            ->with($orderData, $items, $siteId)
            ->andReturn($mockOrder);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentService->shouldReceive('createPayment')
            ->once()
            ->with($mockOrder->id, $paymentData, $siteId)
            ->andReturn($mockPayment);

        $result = $this->service->createOrderWithPayment(
            $orderData,
            $items,
            $siteId,
            $paymentData,
            $mockOrderCreator
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('order', $result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertSame($mockOrder, $result['order']);
        $this->assertSame($mockPayment, $result['payment']);
    }

    public function testCreateOrderWithPaymentWithoutPaymentMethod(): void
    {
        $orderData = ['status' => 'pending'];
        $items = [['product_id' => 1, 'quantity' => 1]];
        $siteId = 1;
        $paymentData = []; // No payment method

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $mockOrderCreator = m::mock(OrderCreationService::class);
        $mockOrderCreator->shouldReceive('create')->andReturn($mockOrder);

        $this->database->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        // Should NOT create payment
        $this->paymentService->shouldNotReceive('createPayment');

        $result = $this->service->createOrderWithPayment(
            $orderData,
            $items,
            $siteId,
            $paymentData,
            $mockOrderCreator
        );

        $this->assertIsArray($result);
        $this->assertSame($mockOrder, $result['order']);
        $this->assertNull($result['payment']);
    }

    public function testCreateOrderWithPaymentWrapsInTransaction(): void
    {
        $orderData = ['status' => 'pending'];
        $items = [['product_id' => 1]];
        $siteId = 1;
        $paymentData = ['payment_method' => 'cash', 'amount' => 100.00];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockPayment = m::mock(Payment::class)->makePartial();

        $mockOrderCreator = m::mock(OrderCreationService::class);
        $mockOrderCreator->shouldReceive('create')->andReturn($mockOrder);
        $this->paymentService->shouldReceive('createPayment')->andReturn($mockPayment);

        // Verify transaction is used
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->createOrderWithPayment(
            $orderData,
            $items,
            $siteId,
            $paymentData,
            $mockOrderCreator
        );

        $this->assertInstanceOf(Order::class, $result['order']);
    }

    public function testCreateOrderWithPaymentRollsBackOnFailure(): void
    {
        $orderData = ['status' => 'pending'];
        $items = [['product_id' => 1]];
        $siteId = 1;
        $paymentData = ['payment_method' => 'cash', 'amount' => 100.00];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $mockOrderCreator = m::mock(OrderCreationService::class);
        $mockOrderCreator->shouldReceive('create')->andReturn($mockOrder);

        // Payment creation fails
        $this->paymentService->shouldReceive('createPayment')
            ->andThrow(new \Exception('Payment failed'));

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment failed');

        $this->service->createOrderWithPayment(
            $orderData,
            $items,
            $siteId,
            $paymentData,
            $mockOrderCreator
        );
    }
}