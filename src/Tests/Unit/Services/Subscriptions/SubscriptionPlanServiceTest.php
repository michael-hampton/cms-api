<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionPlanService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class SubscriptionPlanServiceTest extends FunctionalTestCase
{
    private $planRepository;
    private $subscriptionRepository;
    private $service;
    private $voucherServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->voucherServiceMock = Mockery::mock(\App\Services\Vouchers\VoucherService::class);

        $this->service = new SubscriptionPlanService(
            $this->planRepository,
            $this->subscriptionRepository,
            $this->voucherServiceMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetAvailablePlans(): void
    {
        $siteId = 1;
        $plans = Mockery::mock(Collection::class);

        $this->planRepository->shouldReceive('getActivePlans')
            ->with($siteId)
            ->once()
            ->andReturn($plans);

        $result = $this->service->getAvailablePlans($siteId);

        $this->assertSame($plans, $result);
    }

    public function testCreatePlanPreparesData(): void
    {
        $data = [
            'name' => 'Premium Plan',
            'price' => '29.99',
            'currency' => 'usd',
            'billing_period' => 'monthly',
            'is_active' => '1'
        ];

        $plan = Mockery::mock(SubscriptionPlan::class);

        $this->planRepository->shouldReceive('create')
            ->with(Mockery::on(function ($prepared) {
                return $prepared['name'] === 'Premium Plan'
                    && $prepared['price'] === 29.99
                    && $prepared['currency'] === 'USD'
                    && $prepared['is_active'] === true
                    && isset($prepared['slug']);
            }))
            ->once()
            ->andReturn($plan);

        $result = $this->service->createPlan($data, 1);

        $this->assertSame($plan, $result);
    }

    public function testSubscribeMemberToPlanThrowsIfAlreadySubscribed(): void
    {
        $this->subscriptionRepository->shouldReceive('hasActiveSubscriptionToPlan')
            ->with(1, 1, 1, false)
            ->once()
            ->andReturn(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Member already has an active subscription to this plan');

        $this->service->subscribeMemberToPlan(1, 1, 1);
    }

    public function testSubscribeMemberToPlanCreatesSubscription(): void
    {
        $subscription = Mockery::mock(Subscription::class);

        $this->subscriptionRepository->shouldReceive('hasActiveSubscriptionToPlan')
            ->with(1, 1, 1, false)
            ->once()
            ->andReturn(false);

        $this->subscriptionRepository->shouldReceive('createSubscription')
            ->with(1, 1, 1, [])
            ->once()
            ->andReturn($subscription);

        $result = $this->service->subscribeMemberToPlan(1, 1, 1);

        $this->assertSame($subscription, $result);
    }

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

    public function testCanMemberSubscribeReturnsFalseIfAlreadySubscribed(): void
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

    public function testCanMemberSubscribeReturnsTrueWhenAllowed(): void
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

    public function testDeletePlanThrowsIfHasActiveSubscriptions(): void
    {
        $this->planRepository->shouldReceive('getSubscriberCount')
            ->with(1)
            ->once()
            ->andReturn(5);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete plan with active subscriptions');

        $this->service->deletePlan(1);
    }

    public function testSubscribeMemberToPlanWithVoucherSuccess(): void
    {
        $memberId = 1;
        $planId = 1;
        $siteId = 1;
        $voucherCode = 'SUB10';

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = $planId;
        $plan->price = 29.99;

        $subscription = Mockery::mock(Subscription::class);

        $this->planRepository->shouldReceive('find')
            ->with($planId)
            ->once()
            ->andReturn($plan);

        // Mock VoucherService validation
        $this->voucherServiceMock->shouldReceive('validateVoucherForSubscription')
            ->with($voucherCode, $planId, $memberId)
            ->andReturn([
                'valid' => true,
                'voucher_id' => 1,
                'discount' => 2.99
            ]);

        $this->subscriptionRepository->shouldReceive('createSubscription')
            ->with($memberId, $planId, $siteId, Mockery::on(function ($data) {
                return $data['voucher_id'] === 1
                    && $data['discount_amount'] === 2.99
                    && $data['original_price'] === 29.99;
            }))
            ->once()
            ->andReturn($subscription);

        $result = $this->service->subscribeMemberToPlanWithVoucher(
            $memberId,
            $planId,
            $siteId,
            $voucherCode
        );

        $this->assertSame($subscription, $result);
    }

    public function testSubscribeMemberToPlanWithInvalidVoucher(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 29.99;

        $this->planRepository->shouldReceive('find')
            ->once()
            ->andReturn($plan);

        $this->voucherServiceMock->shouldReceive('validateVoucherForSubscription')
            ->andReturn([
                'valid' => false,
                'message' => 'Voucher not found'
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Voucher not found');

        $this->service->subscribeMemberToPlanWithVoucher(1, 1, 1, 'INVALID');
    }

    public function testSubscribeMemberToPlanWithoutVoucher(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 29.99;

        $subscription = Mockery::mock(Subscription::class);

        $this->planRepository->shouldReceive('find')
            ->once()
            ->andReturn($plan);

        $this->subscriptionRepository->shouldReceive('createSubscription')
            ->with(1, 1, 1, Mockery::on(function ($data) {
                return $data['voucher_id'] === null
                    && $data['discount_amount'] === 0
                    && $data['original_price'] === 29.99;
            }))
            ->once()
            ->andReturn($subscription);

        $result = $this->service->subscribeMemberToPlanWithVoucher(1, 1, 1, null);

        $this->assertSame($subscription, $result);
    }

    public function testSubscribeMemberToPlanWithVoucherThrowsWhenPlanNotFound(): void
    {
        $this->planRepository->shouldReceive('find')
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Plan not found');

        $this->service->subscribeMemberToPlanWithVoucher(1, 999, 1, 'SUB10');
    }
}