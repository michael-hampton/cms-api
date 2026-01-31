<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\OneTimeSubscriptionService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class OneTimeSubscriptionServiceTest extends FunctionalTestCase
{
    private $subscriptionRepository;
    private $planRepository;
    private $service;
    private $databaseMock;
    private $orderRepository;

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

        $result = $this->service->getOneTimePlans(1);

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
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

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

        $result = $this->service->createOneTimeSubscription(
            1, // member_id
            1, // plan_id
            'digital',
            1, // site_id
            null,
            0
        );

        $this->assertInstanceOf(Subscription::class, $result);
    }

    public function testCreateOneTimeSubscriptionFailsWithInvalidPlan(): void
    {
        $this->planRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid one-time subscription plan');

        $this->service->createOneTimeSubscription(1, 999, 'digital', 1, null, 0);
    }

    public function testCreateOneTimeSubscriptionFailsWithInvalidDeliveryType(): void
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(false);

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Digital delivery not available');

        $this->service->createOneTimeSubscription(1, 1, 'digital', 1, null, 0);
    }

    public function testActivateSubscriptionSuccess(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->with(1, ['status' => 'active'])
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

        $this->assertTrue(true); // If no exception, test passes
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->planRepository = m::mock(SubscriptionPlanRepository::class);
        $this->databaseMock = m::mock(Database::class);
        $this->orderRepository = m::mock(OrderRepository::class);

        $this->service = new OneTimeSubscriptionService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->databaseMock,
            $this->orderRepository
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}