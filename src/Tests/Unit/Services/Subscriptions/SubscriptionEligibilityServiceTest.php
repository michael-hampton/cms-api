<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Exceptions\Subscriptions\PlanNotFoundException;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionEligibilityService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionEligibilityServiceTest extends TestCase
{
    private SubscriptionEligibilityService $service;
    private $planRepository;
    private $subscriptionRepository;

    public function testCanMemberSubscribeReturnsFalseForInactivePlan(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = false;

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $result = $this->service->canMemberSubscribe(1, 1, 1);

        $this->assertFalse($result['can_subscribe']);
        $this->assertEquals('Plan not available', $result['reason']);
    }

    public function testCanMemberSubscribeReturnsFalseForNonexistentPlan(): void
    {
        $this->planRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->canMemberSubscribe(1, 999, 1);

        $this->assertFalse($result['can_subscribe']);
        $this->assertEquals('Plan not available', $result['reason']);
    }

    public function testCanMemberSubscribeReturnsFalseIfAlreadySubscribedToSamePlan(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = true;

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->subscriptionRepository->shouldReceive('hasActiveSubscriptionToPlan')
            ->with(1, 1, 1)
            ->once()
            ->andReturn(true);

        $result = $this->service->canMemberSubscribe(1, 1, 1);

        $this->assertFalse($result['can_subscribe']);
        $this->assertEquals('Already subscribed to this plan', $result['reason']);
    }

    public function testCanMemberSubscribeReturnsFalseIfHasActiveSubscriptionToDifferentPlan(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = true;

        $activeSubscription = Mockery::mock(Subscription::class)->makePartial();
        $activeSubscription->plan_name = 'Premium Plan';

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->subscriptionRepository->shouldReceive('hasActiveSubscriptionToPlan')
            ->with(1, 1, 1)
            ->once()
            ->andReturn(false);

        $this->subscriptionRepository->shouldReceive('getActiveSubscriptionForMember')
            ->with(1, 1)
            ->once()
            ->andReturn($activeSubscription);

        $result = $this->service->canMemberSubscribe(1, 1, 1);

        $this->assertFalse($result['can_subscribe']);
        $this->assertEquals('Already has an active subscription', $result['reason']);
        $this->assertEquals('Premium Plan', $result['current_plan']);
    }

    public function testCanMemberSubscribeReturnsTrueWhenEligible(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = true;

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->subscriptionRepository->shouldReceive('hasActiveSubscriptionToPlan')
            ->with(1, 1, 1)
            ->once()
            ->andReturn(false);

        $this->subscriptionRepository->shouldReceive('getActiveSubscriptionForMember')
            ->with(1, 1)
            ->once()
            ->andReturn(null);

        $result = $this->service->canMemberSubscribe(1, 1, 1);

        $this->assertTrue($result['can_subscribe']);
        $this->assertSame($plan, $result['plan']);
    }

    public function testEnsurePlanExistsThrowsForNonexistentPlan(): void
    {
        $this->planRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(PlanNotFoundException::class);
        $this->expectExceptionMessage('Plan with ID 999 not found');

        $this->service->ensurePlanExists(999);
    }

    public function testEnsurePlanExistsDoesNotThrowWhenPlanExists(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class);

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->service->ensurePlanExists(1);

        $this->assertTrue(true); // No exception thrown
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);

        $this->service = new SubscriptionEligibilityService(
            $this->planRepository,
            $this->subscriptionRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}