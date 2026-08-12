<?php

namespace App\Tests\Unit\Services\Billing;

use App\Framework\Database\Database;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\PaymentMethodRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentService;
use App\Tests\Unit\UnitTestCase;
use Mockery as m;

class PaymentServiceTest extends UnitTestCase
{
    private $paymentRepository;
    private $paymentMethodRepository;
    private $orderRepository;
    private $subscriptionRepository;
    private $databaseMock;
    private PaymentService $service;

    protected function setUp(): void
    {

        $this->paymentRepository = m::mock(PaymentRepository::class);
        $this->paymentMethodRepository = m::mock(PaymentMethodRepository::class);
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->databaseMock = m::mock(Database::class);
        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);

        $this->service = new PaymentService(
            $this->paymentRepository,
            $this->paymentMethodRepository,
            $this->orderRepository,
            $this->subscriptionRepository,
            $this->databaseMock
        );
    }
    public function testCreatePaymentSuccessfully()
    {
        $orderId = 1;
        $siteId = 1;
        $data = [
            'payment_method' => 'stripe',
            'amount' => 100.00,
            'currency' => 'GBP'
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = $orderId;
        $mockOrder->total = 100.00;
        $mockOrder->currency = 'GBP';

        $mockPaymentMethod = m::mock(PaymentMethod::class)->makePartial();
        $mockPaymentMethod->code = 'stripe';
        $mockPaymentMethod->provider = 'stripe';
        $mockPaymentMethod->shouldReceive('isActive')->andReturn(true);

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->paymentMethodRepository->shouldReceive('findByCode')
            ->once()
            ->with('stripe')
            ->andReturn($mockPaymentMethod);

        $this->paymentRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($paymentData) use ($orderId, $siteId) {
                return $paymentData['order_id'] === $orderId
                    && $paymentData['site_id'] === $siteId
                    && $paymentData['payment_method'] === 'stripe'
                    && $paymentData['amount'] == 100.00
                    && $paymentData['status'] === 'pending';
            }))
            ->andReturn($mockPayment);

        $result = $this->service->createPayment($orderId, $data, $siteId);

        $this->assertSame($mockPayment, $result);
    }

    public function testCreatePaymentThrowsExceptionWhenOrderNotFound()
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

        $this->service->createPayment(999, ['payment_method' => 'stripe'], 1);
    }

    public function testCreatePaymentThrowsExceptionForInvalidPaymentMethod()
    {
        $mockOrder = m::mock(Order::class)->makePartial();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->andReturn($mockOrder);

        $this->paymentMethodRepository->shouldReceive('findByCode')
            ->once()
            ->with('invalid')
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or inactive payment method');

        $this->service->createPayment(1, ['payment_method' => 'invalid'], 1);
    }

    public function testProcessPaymentSuccessfully()
    {
        $paymentId = 1;
        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->order_id = 1;
        $mockPayment->status = 'pending';
        $mockPayment->transaction_id = null;
        $mockPayment->metadata = [];

        $mockPayment->shouldReceive('isPending')->andReturn(true);
        $mockPayment->shouldReceive('canBeRetried')->andReturn(false);

        $updatedPayment = m::mock(Payment::class)->makePartial();
        $updatedPayment->status = 'processing';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->twice()
            ->with($paymentId)
            ->andReturn($mockPayment, $updatedPayment);

        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->with($paymentId, m::on(function ($data) {
                return $data['status'] === 'processing';
            }));

        $result = $this->service->processPayment($paymentId, ['transaction_id' => 'txn_123']);

        $this->assertEquals('processing', $result->status);
    }

    public function testCompletePaymentSuccessfully()
    {
        $paymentId = 1;
        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->order_id = 1;
        $mockPayment->status = 'processing';
        $mockPayment->metadata = [];

        $mockPayment->shouldReceive('isCompleted')->andReturn(false);

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->payment_status = 'unpaid';

        $completedPayment = m::mock(Payment::class)->makePartial();
        $completedPayment->status = 'completed';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->twice()
            ->with($paymentId)
            ->andReturn($mockPayment, $completedPayment);

        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->with($paymentId, m::on(function ($data) {
                return $data['status'] === 'completed' && isset($data['paid_at']);
            }));

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(1, ['payment_status' => 'paid']);

        $result = $this->service->completePayment($paymentId);

        $this->assertEquals('completed', $result->status);
    }

    public function testFailPaymentSuccessfully()
    {
        $paymentId = 1;
        $errorMessage = 'Card declined';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->order_id = 1;
        $mockPayment->metadata = [];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->payment_status = 'unpaid';

        $failedPayment = m::mock(Payment::class)->makePartial();
        $failedPayment->status = 'failed';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->twice()
            ->with($paymentId)
            ->andReturn($mockPayment, $failedPayment);

        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->with($paymentId, m::on(function ($data) use ($errorMessage) {
                return $data['status'] === 'failed'
                    && $data['error_message'] === $errorMessage
                    && isset($data['failed_at']);
            }));

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(1, ['payment_status' => 'failed']);

        $result = $this->service->failPayment($paymentId, $errorMessage);

        $this->assertEquals('failed', $result->status);
    }

    public function testCancelPaymentSuccessfully()
    {
        $paymentId = 1;
        $reason = 'Customer cancelled';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->order_id = 1;
        $mockPayment->metadata = [];

        $mockPayment->shouldReceive('isCompleted')->andReturn(false);

        $cancelledPayment = m::mock(Payment::class)->makePartial();
        $cancelledPayment->status = 'cancelled';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->twice()
            ->with($paymentId)
            ->andReturn($mockPayment, $cancelledPayment);

        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->with($paymentId, m::on(function ($data) use ($reason) {
                return $data['status'] === 'cancelled'
                    && $data['metadata']['cancellation_reason'] === $reason;
            }));

        $result = $this->service->cancelPayment($paymentId, $reason);

        $this->assertEquals('cancelled', $result->status);
    }

    public function testCancelPaymentThrowsExceptionForCompletedPayment()
    {
        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->shouldReceive('isCompleted')->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->once()
            ->andReturn($mockPayment);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot cancel completed payment');

        $this->service->cancelPayment(1);
    }

    public function testRefundPaymentSuccessfully()
    {
        $paymentId = 1;
        $amount = 50.00;
        $reason = 'Customer request';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->order_id = 1;
        $mockPayment->amount = 100.00;
        $mockPayment->metadata = [];

        $mockPayment->shouldReceive('canBeRefunded')->andReturn(true);

        $refundedPayment = m::mock(Payment::class)->makePartial();
        $refundedPayment->status = 'refunded';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->twice()
            ->with($paymentId)
            ->andReturn($mockPayment, $refundedPayment);

        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->with($paymentId, m::on(function ($data) use ($amount, $reason) {
                return $data['status'] === 'refunded'
                    && $data['metadata']['refund_amount'] == $amount
                    && $data['metadata']['refund_reason'] === $reason;
            }));

        $result = $this->service->refundPayment($paymentId, $amount, $reason);

        $this->assertEquals('refunded', $result->status);
    }

    public function testRefundPaymentThrowsExceptionWhenAmountExceedsPayment()
    {
        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->amount = 100.00;
        $mockPayment->shouldReceive('canBeRefunded')->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->once()
            ->andReturn($mockPayment);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund amount cannot exceed payment amount');

        $this->service->refundPayment(1, 150.00, 'test');
    }

    public function testRetryPaymentSuccessfully()
    {
        $paymentId = 1;
        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->order_id = 1;

        $mockPayment->shouldReceive('canBeRetried')->andReturn(true);

        $retriedPayment = m::mock(Payment::class)->makePartial();
        $retriedPayment->status = 'pending';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->twice()
            ->with($paymentId)
            ->andReturn($mockPayment, $retriedPayment);

        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->with($paymentId, m::on(function ($data) {
                return $data['status'] === 'pending'
                    && $data['error_message'] === null
                    && $data['failed_at'] === null;
            }));

        $result = $this->service->retryPayment($paymentId);

        $this->assertEquals('pending', $result->status);
    }

    public function testValidatePaymentAmountReturnsTrue()
    {
        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->total = 100.00;

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $result = $this->service->validatePaymentAmount(1, 100.00);

        $this->assertTrue($result);
    }

    public function testValidatePaymentAmountReturnsFalse()
    {
        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->total = 100.00;

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($mockOrder);

        $result = $this->service->validatePaymentAmount(1, 150.00);

        $this->assertFalse($result);
    }


    public function testCreateSubscriptionPaymentSuccessfully()
    {
        $subscriptionId = 1;
        $data = [
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe'
        ];

        $mockSubscription = m::mock(\App\Models\Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->site_id = 1;
        $mockSubscription->price = 29.99;
        $mockSubscription->currency = 'USD';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;
        $mockPayment->subscription_id = $subscriptionId;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock Subscription::find
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->paymentRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($paymentData) use ($subscriptionId) {
                return $paymentData['subscription_id'] === $subscriptionId
                    && $paymentData['order_id'] === null
                    && $paymentData['status'] === 'pending';
            }))
            ->andReturn($mockPayment);

        $result = $this->service->createSubscriptionPayment($subscriptionId, $data);

        $this->assertSame($mockPayment, $result);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}