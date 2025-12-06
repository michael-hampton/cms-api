<?php

namespace App\Tests\Unit\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Payment\PayPalPaymentProcessor;
use App\Services\Payment\StripePaymentProcessor;
use App\Services\SubscriptionCheckoutService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class SubscriptionCheckoutServiceTest extends FunctionalTestCase
{
    private $planRepository;
    private $subscriptionRepository;
    private $paymentMethodRepository;
    private $stripeProcessor;
    private $paypalProcessor;
    private $service;

    public function testGetSubscriptionPlanReturnsplan(): void
    {
        $plan = m::mock(SubscriptionPlan::class);

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $result = $this->service->getSubscriptionPlan(1);

        $this->assertSame($plan, $result);
    }

    public function testHasActiveSubscriptionReturnsTrueWhenActive(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 1;

        $this->subscriptionRepository->shouldReceive('getActiveSubscriptionForMember')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $result = $this->service->hasActiveSubscription(1, 1);

        $this->assertTrue($result);
    }

    public function testProcessSubscriptionCheckoutSucceeds(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->is_active = true;
        $plan->price = 29.99;
        $plan->currency = 'USD';
        $plan->billing_period = 'monthly';

        $paymentMethod = m::mock(\App\Models\PaymentMethod::class)->makePartial();;
        $paymentMethod->is_active = true;

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->paymentMethodRepository->shouldReceive('findByCode')
            ->with('stripe')
            ->once()
            ->andReturn($paymentMethod);

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $this->subscriptionRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($params) {
                // Assert/validate parts of the params array
                return isset($params['member_id'])
                    && $params['member_id'] === 1
                    && isset($params['plan_id'])
                    && $params['plan_id'] === 1
                    && isset($params['status'])
                    && $params['status'] === 'pending';
            }))
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, ['payment_intent_id' => 'pi_123', 'payment_subscription_id' => 1])
            ->andReturn($subscription);


        $this->stripeProcessor->shouldReceive('processSubscriptionPayment')
            ->once()
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_123',
                'subscription_id' => 1
            ]);

        $data = [
            'subscription_plan_id' => 1,
            'payment_method' => 'stripe'
        ];

        $result = $this->service->processSubscriptionCheckout(1, $data, 1);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('subscription_id', $result);
    }

    public function testProcessSubscriptionCheckoutFailsWithInvalidPlan(): void
    {
        $this->planRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $data = [
            'subscription_plan_id' => 999,
            'payment_method' => 'stripe'
        ];

        $result = $this->service->processSubscriptionCheckout(1, $data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid subscription plan', $result['message']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = m::mock(SubscriptionPlanRepository::class);
        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->paymentMethodRepository = m::mock(PaymentMethodRepository::class);
        $this->stripeProcessor = m::mock(StripePaymentProcessor::class);
        $this->paypalProcessor = m::mock(PayPalPaymentProcessor::class);

        $this->service = new SubscriptionCheckoutService(
            $this->planRepository,
            $this->subscriptionRepository,
            $this->paymentMethodRepository,
            $this->stripeProcessor,
            $this->paypalProcessor,
            \App\Framework\Database\Database::getInstance()
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}