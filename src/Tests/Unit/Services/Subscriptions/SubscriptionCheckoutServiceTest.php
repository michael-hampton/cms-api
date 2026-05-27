<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\ResolvedSubscriptionPrice;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Database\Database;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\PayPalPaymentProcessor;
use App\Services\Subscriptions\SubscriptionCheckoutService;
use App\Services\Subscriptions\SubscriptionCheckoutPreparationResult;
use App\Services\Subscriptions\SubscriptionCheckoutPreparationService;
use App\Services\Subscriptions\SubscriptionPaymentService;
use App\Services\Vouchers\VoucherService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class SubscriptionCheckoutServiceTest extends FunctionalTestCase
{
    private $planRepository;
    private $subscriptionRepository;
    private $preparationService;
    private $subscriptionPaymentService;
    private $paypalProcessor;
    private $voucherService;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = m::mock(SubscriptionPlanRepository::class);
        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->preparationService = m::mock(SubscriptionCheckoutPreparationService::class);
        $this->subscriptionPaymentService = m::mock(SubscriptionPaymentService::class);
        $this->paypalProcessor = m::mock(PayPalPaymentProcessor::class);
        $this->voucherService = m::mock(VoucherService::class);

        $this->service = new SubscriptionCheckoutService(
            $this->planRepository,
            $this->subscriptionRepository,
            $this->preparationService,
            $this->subscriptionPaymentService,
            $this->paypalProcessor,
            $this->voucherService,
            Database::getInstance()
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testProcessSubscriptionCheckoutSucceeds(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->is_active = true;
        $plan->price = 29.99;
        $plan->currency = 'USD';
        $plan->billing_period = 'monthly';

        $paymentMethod = $this->createPaymentMethod();
        $this->expectPreparation(1, ['subscription_plan_id' => 1, 'payment_method' => 'stripe'], 1, $plan, $paymentMethod);

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan = $plan;

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
            ->with(1, ['payment_intent_id' => 'pi_123', 'payment_subscription_id' => 1, 'status' => 'active'])
            ->andReturn($subscription);


        $this->subscriptionPaymentService->shouldReceive('processStripeSubscriptionPayment')
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

    public function testProcessSubscriptionCheckoutWithVoucherSucceeds(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->is_active = true;
        $plan->price = 29.99;
        $plan->currency = 'USD';
        $plan->billing_period = 'monthly';
        $plan->shouldReceive('getPremiumAccessGrants')->andReturn([]);
        $plan->shouldReceive('grantsPremiumAccess')->andReturn(false);

        $paymentMethod = $this->createPaymentMethod();
        $this->expectPreparation(
            1,
            ['subscription_plan_id' => 1, 'payment_method' => 'stripe', 'voucher_code' => 'SAVE10'],
            1,
            $plan,
            $paymentMethod,
            new ResolvedSubscriptionPrice(1, SubscriptionType::DIGITAL->value, 29.99, 0, 24.99, 'GBP', 5.00, 1)
        );

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->voucher_id = 1;
        $subscription->plan = $plan;
        $subscription->discount_amount = 5;
        $subscription->shouldReceive('grantPremiumAccess')->andReturn(null);
        $subscription->shouldReceive('update')->andReturn(true);

        $this->subscriptionRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($params) {
                return $params['member_id'] == 1
                    && $params['plan_id'] == 1
                    && $params['price'] == 24.99
                    && $params['original_price'] == 29.99
                    && $params['discount_amount'] == 5;
                //&& $params['voucher_id'] === 1;
            }))
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, ['payment_intent_id' => 'pi_123', 'payment_subscription_id' => 'sub_123', 'status' => 'active'])
            ->andReturn($subscription);

        $this->subscriptionPaymentService->shouldReceive('processStripeSubscriptionPayment')
            ->once()
            ->with($subscription, $plan, m::type('array'))
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_123',
                'subscription_id' => 'sub_123',
                'discount_applied' => true
            ]);

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->with(1, 1, 5.00)
            ->andReturn(true);

        $data = [
            'subscription_plan_id' => 1,
            'payment_method' => 'stripe',
            'voucher_code' => 'SAVE10'
        ];

        $result = $this->service->processSubscriptionCheckout(1, $data, 1);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('subscription_id', $result);
    }

    public function testProcessSubscriptionCheckoutFailsWithInvalidVoucher(): void
    {
        $this->preparationService->shouldReceive('prepare')
            ->once()
            ->with(1, ['subscription_plan_id' => 1, 'payment_method' => 'stripe', 'voucher_code' => 'INVALID'], 1)
            ->andThrow(new \InvalidArgumentException('Invalid or expired voucher code'));

        $data = [
            'subscription_plan_id' => 1,
            'payment_method' => 'stripe',
            'voucher_code' => 'INVALID'
        ];

        $result = $this->service->processSubscriptionCheckout(1, $data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid or expired voucher', $result['message']);
    }

    public function testProcessSubscriptionCheckoutFailsWithInvalidPlan(): void
    {
        $this->preparationService->shouldReceive('prepare')
            ->once()
            ->with(1, ['subscription_plan_id' => 999, 'payment_method' => 'stripe'], 1)
            ->andThrow(new \Exception('Invalid subscription plan'));

        $data = [
            'subscription_plan_id' => 999,
            'payment_method' => 'stripe'
        ];

        $result = $this->service->processSubscriptionCheckout(1, $data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid subscription plan', $result['message']);
    }

    public function testProcessSubscriptionCheckoutFailsWithInactivePlan(): void
    {
        $this->preparationService->shouldReceive('prepare')
            ->once()
            ->with(1, ['subscription_plan_id' => 1, 'payment_method' => 'stripe'], 1)
            ->andThrow(new \Exception('Invalid subscription plan'));

        $data = [
            'subscription_plan_id' => 1,
            'payment_method' => 'stripe'
        ];

        $result = $this->service->processSubscriptionCheckout(1, $data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid subscription plan', $result['message']);
    }

    public function testProcessSubscriptionCheckoutFailsWithInvalidPaymentMethod(): void
    {
        $this->preparationService->shouldReceive('prepare')
            ->once()
            ->with(1, ['subscription_plan_id' => 1, 'payment_method' => 'invalid'], 1)
            ->andThrow(new \Exception('Invalid payment method'));

        $data = [
            'subscription_plan_id' => 1,
            'payment_method' => 'invalid'
        ];

        $result = $this->service->processSubscriptionCheckout(1, $data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid payment method', $result['message']);
    }

    public function testGetSubscriptionPlanBySlugReturnsPlan(): void
    {
        $plan = m::mock(SubscriptionPlan::class);

        $this->planRepository->shouldReceive('findBySlug')
            ->with('premium-monthly')
            ->once()
            ->andReturn($plan);

        $result = $this->service->getSubscriptionPlanBySlug('premium-monthly');

        $this->assertSame($plan, $result);
    }

    public function testGetSubscriptionPlanBySlugReturnsNullWhenNotFound(): void
    {
        $this->planRepository->shouldReceive('findBySlug')
            ->with('nonexistent')
            ->once()
            ->andReturn(null);

        $result = $this->service->getSubscriptionPlanBySlug('nonexistent');

        $this->assertNull($result);
    }

    public function testProcessSubscriptionCheckoutMarksSubscriptionFailedOnPaymentFailure(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->is_active = true;
        $plan->price = 29.99;
        $plan->currency = 'USD';
        $plan->billing_period = 'monthly';

        $paymentMethod = $this->createPaymentMethod();
        $this->expectPreparation(1, ['subscription_plan_id' => 1, 'payment_method' => 'stripe'], 1, $plan, $paymentMethod);

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan = $plan;
        $subscription->voucher_id = null;

        $this->subscriptionRepository->shouldReceive('create')->once()->andReturn($subscription);

        $this->subscriptionPaymentService->shouldReceive('processStripeSubscriptionPayment')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Card declined']);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, ['status' => 'failed']);

        $data = ['subscription_plan_id' => 1, 'payment_method' => 'stripe'];
        $result = $this->service->processSubscriptionCheckout(1, $data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Card declined', $result['message']);
    }

    public function testProcessSubscriptionCheckoutUsesPaypalProcessorWithoutVoucherSpecificMethod(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->is_active = true;
        $plan->price = 29.99;
        $plan->currency = 'USD';
        $plan->billing_period = 'monthly';
        $plan->shouldReceive('getPremiumAccessGrants')->andReturn([]);
        $plan->shouldReceive('grantsPremiumAccess')->andReturn(false);

        $paymentMethod = $this->createPaymentMethod();
        $this->expectPreparation(
            1,
            ['subscription_plan_id' => 1, 'payment_method' => 'paypal', 'voucher_code' => 'SAVE10'],
            1,
            $plan,
            $paymentMethod,
            new ResolvedSubscriptionPrice(1, SubscriptionType::DIGITAL->value, 29.99, 0, 24.99, 'GBP', 5.00, 1)
        );

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan = $plan;
        $subscription->voucher_id = 1;
        $subscription->discount_amount = 5.00;

        $this->subscriptionRepository->shouldReceive('create')->once()->andReturn($subscription);

        $this->paypalProcessor->shouldReceive('processSubscriptionPayment')
            ->once()
            ->with($subscription, $plan, ['subscription_plan_id' => 1, 'payment_method' => 'paypal', 'voucher_code' => 'SAVE10'])
            ->andReturn(['success' => true, 'redirect_url' => 'https://paypal.test']);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, ['payment_intent_id' => null, 'payment_subscription_id' => null, 'status' => 'active'])
            ->andReturn($subscription);

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->with(1, 1, 5.00)
            ->andReturn(true);

        $result = $this->service->processSubscriptionCheckout(
            1,
            ['subscription_plan_id' => 1, 'payment_method' => 'paypal', 'voucher_code' => 'SAVE10'],
            1
        );

        $this->assertTrue($result['success']);
        $this->assertSame('https://paypal.test', $result['redirect_url']);
    }

    private function expectPreparation(
        int $memberId,
        array $data,
        int $siteId,
        SubscriptionPlan $plan,
        object $paymentMethod,
        ?ResolvedSubscriptionPrice $resolvedPrice = null
    ): void {
        $resolvedPrice ??= new ResolvedSubscriptionPrice(
            1,
            SubscriptionType::DIGITAL->value,
            $plan->price,
            0,
            $plan->price,
            'GBP',
            0,
            null
        );

        $this->preparationService->shouldReceive('prepare')
            ->with($memberId, $data, $siteId)
            ->once()
            ->andReturn(new SubscriptionCheckoutPreparationResult($plan, $paymentMethod, $resolvedPrice));
    }

    private function createPaymentMethod(): PaymentMethod
    {
        $paymentMethod = m::mock(PaymentMethod::class)->makePartial();
        $paymentMethod->is_active = true;

        return $paymentMethod;
    }
}
