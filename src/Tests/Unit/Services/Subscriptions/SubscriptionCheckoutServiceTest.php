<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\ResolvedSubscriptionPrice;
use App\DTO\Vouchers\VoucherValidationResult;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Voucher;
use App\Repositories\Billing\PaymentMethodRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\PayPalPaymentProcessor;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\Calculators\SubscriptionPricingResolver;
use App\Services\Subscriptions\SubscriptionCheckoutService;
use App\Services\Subscriptions\SubscriptionEligibilityService;
use App\Services\Vouchers\VoucherService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class SubscriptionCheckoutServiceTest extends FunctionalTestCase
{
    private $planRepository;
    private $subscriptionRepository;
    private $paymentMethodRepository;
    private $stripeProcessor;
    private $paypalProcessor;
    private $voucherService;
    private $service;
    private $eligibilityService;
    private SubscriptionPricingResolver $pricingResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = m::mock(SubscriptionPlanRepository::class);
        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->paymentMethodRepository = m::mock(PaymentMethodRepository::class);
        $this->stripeProcessor = m::mock(StripePaymentProcessor::class);
        $this->paypalProcessor = m::mock(PayPalPaymentProcessor::class);
        $this->voucherService = m::mock(VoucherService::class);
        $this->eligibilityService = m::mock(SubscriptionEligibilityService::class);
        $this->pricingResolver = m::mock(SubscriptionPricingResolver::class);

        $this->service = new SubscriptionCheckoutService(
            $this->planRepository,
            $this->subscriptionRepository,
            $this->paymentMethodRepository,
            $this->stripeProcessor,
            $this->paypalProcessor,
            $this->voucherService,
            $this->eligibilityService,
            $this->pricingResolver,
            Database::getInstance()
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

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

        $this->setPricingResolverExpectations($plan);

        $this->setEligibilityServiceMock(true);

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

        $this->setPricingResolverExpectations($plan, 'SAVE10');

        $this->setEligibilityServiceMock(true);

        $paymentMethod = m::mock(\App\Models\PaymentMethod::class)->makePartial();
        $paymentMethod->is_active = true;

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->code = 'SAVE10';

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->paymentMethodRepository->shouldReceive('findByCode')
            ->with('stripe')
            ->once()
            ->andReturn($paymentMethod);

        // Update to expect VoucherValidationResult object
        $voucherResult = new VoucherValidationResult(
            valid: true,
            message: 'Voucher applied successfully',
            discount: 5.00,
            voucher: $voucher,
            finalPrice: 24.99
        );

        $this->voucherService->shouldReceive('getVoucherById')
            ->with(1)
            ->once()
            ->andReturn($voucher);

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->with('SAVE10', 1, 1)
            ->once()
            ->andReturn($voucherResult);

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

        $this->stripeProcessor->shouldReceive('processSubscriptionPaymentWithVoucher')
            ->once()
            ->with($subscription, $plan, $voucher, m::type('array'))
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
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->is_active = true;
        $plan->price = 22.99;

        $paymentMethod = m::mock(\App\Models\PaymentMethod::class)->makePartial();
        $paymentMethod->is_active = true;

        $this->setPricingResolverExpectations($plan, 'INVALID');
        $this->setEligibilityServiceMock(true);

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->paymentMethodRepository->shouldReceive('findByCode')
            ->with('stripe')
            ->once()
            ->andReturn($paymentMethod);

        $voucherValidationResult = new VoucherValidationResult(valid: false, message: 'Invalid or expired voucher code');

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->with('INVALID', 1, 1)
            ->once()
            ->andReturn($voucherValidationResult);

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
        $this->planRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->paymentMethodRepository->shouldReceive('findByCode')
            ->with('stripe')
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

    public function testProcessSubscriptionCheckoutFailsWithInactivePlan(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = false;

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->paymentMethodRepository->shouldReceive('findByCode')
            ->with('stripe')
            ->once()
            ->andReturn(null);

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
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = true;

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->paymentMethodRepository->shouldReceive('findByCode')
            ->with('invalid')
            ->once()
            ->andReturn(null);

        $data = [
            'subscription_plan_id' => 1,
            'payment_method' => 'invalid'
        ];

        $result = $this->service->processSubscriptionCheckout(1, $data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid payment method', $result['message']);
    }

    private function setEligibilityServiceMock(bool $canSubscribe): void
    {
        $this->eligibilityService->shouldReceive('canMemberSubscribe')
            ->with(1, 1, 1, true)
            ->andReturn(['can_subscribe' => $canSubscribe]);
    }

    private function setPricingResolverExpectations(SubscriptionPlan $plan, ?string $voucherCode = null)
    {
        $result = new ResolvedSubscriptionPrice(1, SubscriptionType::DIGITAL->value, $plan->price, 0, $plan->price, 'GBP', 0, null);

        $this->pricingResolver->shouldReceive('resolve')
            ->once()
            ->with($plan, ['variant' => SubscriptionType::DIGITAL->value, 'pricing_tier_id' => NULL, 'voucher_code' => $voucherCode], 1)
            ->andReturn($result);
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

    public function testHasActiveSubscriptionReturnsFalseWhenNoSubscription(): void
    {
        $this->subscriptionRepository->shouldReceive('getActiveSubscriptionForMember')
            ->with(1)
            ->once()
            ->andReturn(null);

        $result = $this->service->hasActiveSubscription(1, 1);

        $this->assertFalse($result);
    }

    public function testHasActiveSubscriptionReturnsFalseWhenDifferentPlan(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 99; // Different plan

        $this->subscriptionRepository->shouldReceive('getActiveSubscriptionForMember')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $result = $this->service->hasActiveSubscription(1, 1);

        $this->assertFalse($result);
    }

    public function testProcessSubscriptionCheckoutMarksSubscriptionFailedOnPaymentFailure(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->is_active = true;
        $plan->price = 29.99;
        $plan->currency = 'USD';
        $plan->billing_period = 'monthly';

        $paymentMethod = m::mock(\App\Models\PaymentMethod::class)->makePartial();
        $paymentMethod->is_active = true;

        $this->setPricingResolverExpectations($plan);
        $this->setEligibilityServiceMock(true);

        $this->planRepository->shouldReceive('find')->with(1)->once()->andReturn($plan);
        $this->paymentMethodRepository->shouldReceive('findByCode')->with('stripe')->once()->andReturn($paymentMethod);

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan = $plan;
        $subscription->voucher_id = null;

        $this->subscriptionRepository->shouldReceive('create')->once()->andReturn($subscription);

        // Payment fails
        $this->stripeProcessor->shouldReceive('processSubscriptionPayment')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Card declined']);

        // Subscription must be marked failed
        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, ['status' => 'failed']);

        $data = ['subscription_plan_id' => 1, 'payment_method' => 'stripe'];
        $result = $this->service->processSubscriptionCheckout(1, $data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Card declined', $result['message']);
    }
}