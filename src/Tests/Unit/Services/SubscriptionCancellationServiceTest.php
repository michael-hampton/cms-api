<?php
// src/Tests/Unit/Services/SubscriptionCancellationServiceTest.php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\PaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Payment\StripePaymentProcessor;
use App\Services\SubscriptionCancellationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class SubscriptionCancellationServiceTest extends FunctionalTestCase
{
    private $subscriptionRepository;
    private $paymentRepository;
    private $stripeProcessor;
    private $databaseMock;
    private SubscriptionCancellationService $service;

    public function testCancelSubscriptionWithStripeAtPeriodEnd(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeProcessor->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_stripe123', true)
            ->andReturn([
                'success' => true,
                'status' => 'active',
                'cancel_at_period_end' => true
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'cancelled'
                    && $data['auto_renew'] === false
                    && !isset($data['end_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => true
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame($mockSubscription, $result['subscription']);
    }

    public function testCancelSubscriptionImmediately(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeProcessor->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_stripe123', false)
            ->andReturn([
                'success' => true,
                'status' => 'canceled'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'cancelled'
                    && $data['auto_renew'] === false
                    && isset($data['end_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => false
        ]);

        $this->assertTrue($result['success']);
    }

    public function testReactivateSubscription(): void
    {
        $subscriptionId = 1;
        $_ENV['APP_ENV'] = 'production';

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'cancelled';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeProcessor->shouldReceive('reactivateSubscription')
            ->once()
            ->with('sub_stripe123')
            ->andReturn([
                'success' => true,
                'status' => 'active'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'active'
                    && $data['auto_renew'] === true
                    && isset($data['end_date'])
                    && isset($data['next_billing_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->reactivateSubscription($subscriptionId);

        $this->assertTrue($result['success']);

        $_ENV['APP_ENV'] = 'testing';
    }

    public function testReactivateSubscriptionThrowsIfAlreadyActive(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active'; // Already active

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only reactivate cancelled subscriptions');

        $this->service->reactivateSubscription($subscriptionId);
    }

    public function testReactivateSubscriptionThrowsIfSubscriptionAlreadyEnded(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'cancelled';
        $mockSubscription->end_date = new \DateTime('-1 day'); // Already ended
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already ended and cannot be reactivated');

        $this->service->reactivateSubscription($subscriptionId);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->paymentRepository = m::mock(PaymentRepository::class);
        $this->stripeProcessor = m::mock(StripePaymentProcessor::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new SubscriptionCancellationService(
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->stripeProcessor,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}