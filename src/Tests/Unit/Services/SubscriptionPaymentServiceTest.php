<?php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\PaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\PaymentService;
use App\Services\SubscriptionPaymentService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery as m;

class SubscriptionPaymentServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $paymentRepository;
    private $subscriptionRepository;
    private $paymentService;
    private $databaseMock;
    private SubscriptionPaymentService $service;

    public function testCreateInitialSubscriptionPaymentSuccessfully(): void
    {
        $subscriptionId = 1;
        $memberId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->member_id = $memberId;
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

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->paymentRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($subscriptionId) {
                return $data['subscription_id'] === $subscriptionId
                    && $data['status'] === 'pending'
                    && isset($data['metadata']['subscription_initial_payment']);
            }))
            ->andReturn($mockPayment);

        $result = $this->service->createInitialSubscriptionPayment($subscriptionId, $memberId);

        $this->assertSame($mockPayment, $result);
    }

    public function testCreateInitialSubscriptionPaymentThrowsWhenSubscriptionNotFound(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->createInitialSubscriptionPayment(999, 1);
    }

    public function testCreateInitialSubscriptionPaymentThrowsWhenMemberMismatch(): void
    {
        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->member_id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->andReturn($mockSubscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription does not belong to member');

        $this->service->createInitialSubscriptionPayment(1, 999);
    }

    public function testCreateRecurringPaymentSuccessfully(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->site_id = 1;
        $mockSubscription->price = 29.99;
        $mockSubscription->currency = 'USD';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('isDueForRenewal')->andReturn(true);

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->paymentRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($subscriptionId) {
                return $data['subscription_id'] === $subscriptionId
                    && $data['status'] === 'pending'
                    && isset($data['metadata']['subscription_renewal']);
            }))
            ->andReturn($mockPayment);

        $result = $this->service->createRecurringPayment($subscriptionId);

        $this->assertSame($mockPayment, $result);
    }

    public function testCreateRecurringPaymentThrowsWhenNotDueForRenewal(): void
    {
        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->shouldReceive('isDueForRenewal')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->andReturn($mockSubscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription is not due for renewal');

        $this->service->createRecurringPayment(1);
    }

    public function testCompleteSubscriptionPaymentSuccessfully(): void
    {
        $paymentId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->subscription_id = 1;
        $mockPayment->shouldReceive('isSubscriptionPayment')->andReturn(true);

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = 1;
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->auto_renew = true;
        $mockSubscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->once()
            ->with($paymentId)
            ->andReturn($mockPayment);

        $this->paymentService->shouldReceive('completePayment')
            ->once()
            ->with($paymentId)
            ->andReturn($mockPayment);

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($mockSubscription);

        $this->subscriptionRepository->shouldReceive('updateLastPaymentDate')
            ->once()
            ->andReturn(true);

        $this->subscriptionRepository->shouldReceive('updateNextBillingDate')
            ->once()
            ->andReturn(true);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($mockSubscription);

        $result = $this->service->completeSubscriptionPayment($paymentId);

        $this->assertSame($mockPayment, $result);
    }

    public function testHandleFailedSubscriptionPaymentMarksAsPastDue(): void
    {
        $paymentId = 1;
        $errorMessage = 'Card declined';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->subscription_id = 1;
        $mockPayment->shouldReceive('isSubscriptionPayment')->andReturn(true);

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = 1;
        $mockSubscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->once()
            ->with($paymentId)
            ->andReturn($mockPayment);

        $this->paymentService->shouldReceive('failPayment')
            ->once()
            ->with($paymentId, $errorMessage)
            ->andReturn($mockPayment);

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($mockSubscription);

        $this->paymentRepository->shouldReceive('countSubscriptionPayments')
            ->once()
            ->with(1, 'failed')
            ->andReturn(1);

        $this->subscriptionRepository->shouldReceive('markAsPastDue')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->handleFailedSubscriptionPayment($paymentId, $errorMessage);

        $this->assertSame($mockPayment, $result);
    }

    public function testHandleFailedSubscriptionPaymentCancelsAfterThreeFailures(): void
    {
        $paymentId = 1;
        $errorMessage = 'Card declined';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->subscription_id = 1;
        $mockPayment->shouldReceive('isSubscriptionPayment')->andReturn(true);

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = 1;
        $mockSubscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->once()
            ->with($paymentId)
            ->andReturn($mockPayment);

        $this->paymentService->shouldReceive('failPayment')
            ->once()
            ->andReturn($mockPayment);

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->andReturn($mockSubscription);

        $this->paymentRepository->shouldReceive('countSubscriptionPayments')
            ->once()
            ->with(1, 'failed')
            ->andReturn(3);

        $this->subscriptionRepository->shouldReceive('markAsPastDue')
            ->once()
            ->andReturn(true);

        $this->subscriptionRepository->shouldReceive('cancelSubscription')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->handleFailedSubscriptionPayment($paymentId, $errorMessage);

        $this->assertSame($mockPayment, $result);
    }

    public function testGetSubscriptionPaymentHistory(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;

        $mockPayments = m::mock(\App\Framework\Support\Collection::class);
        $mockPayments->shouldReceive('where')
            ->with('status', 'completed')
            ->andReturnSelf();
        $mockPayments->shouldReceive('sum')
            ->with('amount')
            ->andReturn(59.98);
        $mockPayments->shouldReceive('where')
            ->with('status', 'failed')
            ->andReturnSelf();
        $mockPayments->shouldReceive('count')
            ->andReturn(1);

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->paymentRepository->shouldReceive('findBySubscriptionId')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockPayments);

        $result = $this->service->getSubscriptionPaymentHistory($subscriptionId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('subscription', $result);
        $this->assertArrayHasKey('payments', $result);
        $this->assertArrayHasKey('total_paid', $result);
        $this->assertArrayHasKey('failed_count', $result);
        $this->assertEquals(59.98, $result['total_paid']);
        $this->assertEquals(1, $result['failed_count']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = m::mock(PaymentRepository::class);
        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->paymentService = m::mock(PaymentService::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new SubscriptionPaymentService(
            $this->paymentRepository,
            $this->subscriptionRepository,
            $this->paymentService,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}