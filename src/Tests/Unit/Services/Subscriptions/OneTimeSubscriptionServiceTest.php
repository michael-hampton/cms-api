<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\Subscriptions\BillingPeriod;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Exceptions\Subscriptions\InvalidDeliveryTypeException;
use App\Exceptions\Subscriptions\InvalidSubscriptionPlanException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Shopping\OneTimeSubscriptionService;
use App\Services\Subscriptions\Calculators\SubscriptionDateCalculator;
use App\Services\Subscriptions\Calculators\SubscriptionPricingCalculator;
use App\Services\Subscriptions\Validators\OneTimePlanValidator;
use App\Services\ValueObjects\Money;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class OneTimeSubscriptionServiceTest extends FunctionalTestCase
{
    private $subscriptionRepository;
    private $planRepository;
    private $service;
    private $databaseMock;
    private $orderRepository;
    private $validator;
    private $dateCalculator;
    private $pricingCalculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->planRepository = m::mock(SubscriptionPlanRepository::class);
        $this->databaseMock = m::mock(Database::class);
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->validator = m::mock(OneTimePlanValidator::class);
        $this->dateCalculator = m::mock(SubscriptionDateCalculator::class);
        $this->pricingCalculator = m::mock(SubscriptionPricingCalculator::class);

        $this->service = new OneTimeSubscriptionService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->databaseMock,
            $this->orderRepository,
            $this->validator,
            $this->dateCalculator,
            $this->pricingCalculator
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testGetOneTimePlansReturnsOnlyOneTimePlans(): void
    {
        $recurringPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $recurringPlan->shouldReceive('isOneTime')->andReturn(false);

        $oneTimePlan = m::mock(SubscriptionPlan::class)->makePartial();
        $oneTimePlan->id = 1;
        $oneTimePlan->name = 'Annual Digital';
        $oneTimePlan->slug = 'annual-digital';
        $oneTimePlan->description = 'One year digital access';
        $oneTimePlan->price = 99.99;
        $oneTimePlan->currency = 'USD';
        $oneTimePlan->billing_period = 'yearly';
        $oneTimePlan->features = ['Feature 1', 'Feature 2'];
        $oneTimePlan->shouldReceive('isOneTime')->andReturn(true);
        $oneTimePlan->shouldReceive('getDeliveryOptions')->andReturn(['digital', 'print']);
        $oneTimePlan->shouldReceive('hasDigitalOption')->andReturn(true);
        $oneTimePlan->shouldReceive('hasPrintOption')->andReturn(true);

        $collection = collect([$recurringPlan, $oneTimePlan]);

        $this->planRepository->shouldReceive('getActivePlans')
            ->with(1)
            ->once()
            ->andReturn($collection);

        $result = $this->service->getOneTimePlansCatalog(1);

        $this->assertCount(1, $result);

        $this->assertEquals('Annual Digital', $result[1]['name']);
        $this->assertArrayHasKey('delivery_options', $result[1]);
        $this->assertCount(2, $result[1]['delivery_options']);
    }

    public function testCreateOneTimeSubscriptionSuccess(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->name = 'Annual Digital';
        $plan->price = 99.99;
        $plan->currency = 'USD';
        $plan->billing_period = 'yearly';

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->validator->shouldReceive('validatePlanForSubscription')
            ->with($plan, 'digital')
            ->once();

        $this->validator->shouldReceive('validateBillingPeriod')
            ->with('yearly')
            ->once()
            ->andReturn(BillingPeriod::YEARLY);

        $startDate = new \DateTimeImmutable('2024-01-01 00:00:00');
        $endDate = new \DateTimeImmutable('2025-01-01 00:00:00');

        $this->dateCalculator->shouldReceive('normalizeStartDate')
            ->with(null)
            ->once()
            ->andReturn($startDate);

        $this->dateCalculator->shouldReceive('calculateEndDate')
            ->with($startDate, BillingPeriod::YEARLY)
            ->once()
            ->andReturn($endDate);

        $basePrice = Money::fromDecimal(99.99, 'USD');
        $discount = Money::fromCents(0, 'USD');

        $this->pricingCalculator->shouldReceive('validateDiscount')
            ->with(m::type(Money::class), m::type(Money::class))
            ->once();

        $this->pricingCalculator->shouldReceive('calculateFinalPrice')
            ->with(m::type(Money::class), m::type(Money::class))
            ->once()
            ->andReturn($basePrice);

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $this->subscriptionRepository->shouldReceive('create')
            ->once()
            ->andReturn($subscription);

        $subscription->shouldReceive('generateDownloadUrl')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $pricing = new SubscriptionPricing(
            20,
            20,
            20,
            20,
            20,
            'digital'
        );

        $result = $this->service->createOneTimeSubscription(
            1, // member_id
            1, // plan_id
            'digital',
            1, // site_id
            null,
            $pricing
        );

        $this->assertInstanceOf(Subscription::class, $result);
    }

    public function testCreateOneTimeSubscriptionFailsWithInvalidPlan(): void
    {
        $this->planRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->validator->shouldReceive('validatePlanForSubscription')
            ->andThrow(new InvalidSubscriptionPlanException('Invalid one-time subscription plan'));

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $pricing = new SubscriptionPricing(
            20,
            20,
            20,
            20,
            20,
            'digital'
        );

        $this->expectException(InvalidSubscriptionPlanException::class);
        $this->expectExceptionMessage('Invalid one-time subscription plan');

        $this->service->createOneTimeSubscription(1, 999, 'digital', 1, null, $pricing);
    }

    public function testCreateOneTimeSubscriptionFailsWithInvalidDeliveryType(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->validator->shouldReceive('validatePlanForSubscription')
            ->with($plan, 'digital')
            ->andThrow(new InvalidDeliveryTypeException('Digital delivery not available'));

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->expectException(InvalidDeliveryTypeException::class);
        $this->expectExceptionMessage('Digital delivery not available');

        $pricing = new SubscriptionPricing(
            20,
            20,
            20,
            20,
            20,
            'digital'
        );

        $this->service->createOneTimeSubscription(1, 1, 'digital', 1, null, $pricing);
    }

    public function testCreateOneTimeSubscriptionWithDiscount(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->name = 'Annual Digital';
        $plan->price = 99.99;
        $plan->currency = 'USD';
        $plan->billing_period = 'yearly';

        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->validator->shouldReceive('validatePlanForSubscription')->once();
        $this->validator->shouldReceive('validateBillingPeriod')->andReturn(BillingPeriod::YEARLY);

        $startDate = new \DateTimeImmutable();
        $endDate = new \DateTimeImmutable('+1 year');

        $this->dateCalculator->shouldReceive('normalizeStartDate')->andReturn($startDate);
        $this->dateCalculator->shouldReceive('calculateEndDate')->andReturn($endDate);

        $basePrice = Money::fromDecimal(99.99, 'USD');
        $discount = Money::fromCents(1000, 'USD'); // $10.00
        $finalPrice = Money::fromDecimal(89.99, 'USD');

        $this->pricingCalculator->shouldReceive('validateDiscount')->once();
        $this->pricingCalculator->shouldReceive('calculateFinalPrice')
            ->andReturn($finalPrice);

        $subscription = m::mock(Subscription::class)->makePartial();
        $this->subscriptionRepository->shouldReceive('create')
            ->with(m::on(function ($data) {
                return $data['price'] == 89.99
                    && $data['discount_amount'] == 10
                    && $data['original_price'] == 99.99;
            }))
            ->andReturn($subscription);

        $subscription->shouldReceive('generateDownloadUrl')->once();
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $pricing = new SubscriptionPricing(
            20,
            1000,
            20,
            20,
            20,
            'digital'
        );

        $result = $this->service->createOneTimeSubscription(
            1, 1, 'digital', 1, null, $pricing // 1000 cents = $10
        );

        $this->assertInstanceOf(Subscription::class, $result);
    }

    public function testCreateOneTimeSubscriptionValidatesNegativeDiscount(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 99.99;
        $plan->currency = 'USD';
        $plan->billing_period = 'yearly';

        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->validator->shouldReceive('validatePlanForSubscription')->once();
        $this->validator->shouldReceive('validateBillingPeriod')->andReturn(BillingPeriod::YEARLY);

        $this->dateCalculator->shouldReceive('normalizeStartDate')
            ->andReturn(new \DateTimeImmutable());
        $this->dateCalculator->shouldReceive('calculateEndDate')
            ->andReturn(new \DateTimeImmutable('+1 year'));

        $this->pricingCalculator->shouldReceive('validateDiscount')
            ->andThrow(new \InvalidArgumentException('Discount amount cannot be negative'));

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount amount cannot be negative');

        $pricing = new SubscriptionPricing(
            20,
            20,
            20,
            20,
            20,
            'digital'
        );

        $this->service->createOneTimeSubscription(
            1, 1, 'digital', 1, null, $pricing
        );
    }

    public function testActivateSubscriptionSuccess(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = SubscriptionStatus::PENDING->value;

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->with(1, ['status' => SubscriptionStatus::ACTIVE->value])
            ->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('updateSubscriptionForOrder')
            ->with(1, 1)
            ->once();

        $this->service->activateSubscription(1, 1);

        $this->assertTrue(true);
    }

    public function testActivateSubscriptionThrowsWhenNotFound(): void
    {
        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->expectException(SubscriptionNotFoundException::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->activateSubscription(999, 1);
    }

    public function testActivateSubscriptionEnforcesStateTransition(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = SubscriptionStatus::ACTIVE->value; // Already active

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot activate subscription with status: active');

        $this->service->activateSubscription(1, 1);
    }

    public function testGetSubscriptionSummaryWithoutOrder(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->price = 99.99;
        $subscription->discount_amount = 10.00;
        $subscription->delivery_type = 'print';
        $subscription->plan = null;
        $subscription->download_expires_at = null;

        $orderRelation = m::mock();
        $orderRelation->shouldReceive('last')->andReturn(null);
        $subscription->shouldReceive('order')->andReturn($orderRelation);
        $subscription->shouldReceive('hasValidDownload')->andReturn(false);
        $subscription->shouldReceive('toArray')->andReturn(['id' => 1]);

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $result = $this->service->getSubscriptionSummary(1);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('payment_breakdown', $result);
        $this->assertTrue($result['payment_breakdown']['is_estimate']);
        $this->assertEquals(1000, $result['payment_breakdown']['shipping_cents']); // $10 shipping
    }

    public function testGetSubscriptionSummaryReturnsNullForNonexistent(): void
    {
        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->getSubscriptionSummary(999);

        $this->assertNull($result);
    }


}