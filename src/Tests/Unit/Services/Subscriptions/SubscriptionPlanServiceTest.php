<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Vouchers\VoucherValidationResult;
use App\Exceptions\Subscriptions\AlreadySubscribedException;
use App\Exceptions\Subscriptions\PlanHasActiveSubscriptionsException;
use App\Exceptions\Subscriptions\PlanNotFoundException;
use App\Framework\Support\Collection;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Voucher;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionEligibilityService;
use App\Services\Subscriptions\SubscriptionPlanService;
use App\Services\Vouchers\VoucherService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class SubscriptionPlanServiceTest extends FunctionalTestCase
{
    private $planRepository;
    private $subscriptionRepository;
    private $service;
    private $voucherServiceMock;
    private $eligibilityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->voucherServiceMock = Mockery::mock(VoucherService::class);
        $this->eligibilityService = Mockery::mock(SubscriptionEligibilityService::class);

        $this->service = new SubscriptionPlanService(
            $this->planRepository,
            $this->subscriptionRepository,
            $this->voucherServiceMock,
            $this->eligibilityService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetActivePlansForSite(): void
    {
        $siteId = 1;
        $plans = Mockery::mock(Collection::class);

        $this->planRepository->shouldReceive('getActivePlans')
            ->with($siteId)
            ->once()
            ->andReturn($plans);

        $result = $this->service->getActivePlansForSite($siteId);

        $this->assertSame($plans, $result);
    }

    public function testCreatePlanPreparesData(): void
    {
        $data = [
            'name' => 'Premium Plan',
            'price' => 29.99,
            'currency' => 'usd',
            'billing_period' => 'monthly',
            'is_active' => '1'
        ];

        $plan = Mockery::mock(SubscriptionPlan::class);

        $this->planRepository->shouldReceive('create')
            ->with(Mockery::on(function ($prepared) {
                return $prepared['name'] === 'Premium Plan'
                    && abs($prepared['price'] - 29.99) < 0.01
                    && $prepared['currency'] === 'USD'
                    && $prepared['is_active'] === true
                    && isset($prepared['slug'])
                    && $prepared['billing_period'] === 'monthly';
            }))
            ->once()
            ->andReturn($plan);

        $result = $this->service->createPlan($data, 1);

        $this->assertSame($plan, $result);
    }

    public function testCreatePlanValidatesBillingPeriod(): void
    {
        $data = [
            'name' => 'Test Plan',
            'billing_period' => 'invalid_period',
            'currency' => 'USD',
            'price' => 29.99
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid billing period: invalid_period');

        $this->service->createPlan($data, 1);
    }

    public function testCreatePlanValidatesCurrency(): void
    {
        $data = [
            'name' => 'Test Plan',
            'price' => 29.99,
            'currency' => 'INVALID',
            'billing_period' => 'monthly'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency INVALID is not supported');

        $this->service->createPlan($data, 1);
    }

    public function testCreatePlanValidatesNegativePrice(): void
    {
        $data = [
            'name' => 'Test Plan',
            'price' => -29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price cannot be negative');

        $this->service->createPlan($data, 1);
    }

    public function testCreatePlanValidatesTrialDays(): void
    {
        $data = [
            'name' => 'Test Plan',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'trial_days' => -5
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Trial days cannot be negative');

        $this->service->createPlan($data, 1);
    }

    public function testUpdatePlanThrowsForNonexistentPlan(): void
    {
        $this->planRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(PlanNotFoundException::class);
        $this->expectExceptionMessage('Plan with ID 999 not found');

        $this->service->updatePlan(999, ['name' => 'Updated'], 1);
    }

    public function testUpdatePlanEnforcesSiteOwnership(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->site_id = 1;

        $this->planRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot update plan from different site');

        $this->service->updatePlan(1, ['name' => 'Updated'], 2); // Different site
    }

    public function testUpdatePlanPreventsSlugChangeWithActiveSubscriptions(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->site_id = 1;
        $plan->slug = 'old-slug';

        $this->planRepository->shouldReceive('find')->andReturn($plan);
        $this->planRepository->shouldReceive('getSubscriberCount')
            ->with(1)
            ->once()
            ->andReturn(5);

        $this->expectException(PlanHasActiveSubscriptionsException::class);
        $this->expectExceptionMessage('Cannot change slug for plan with active subscriptions');

        $this->service->updatePlan(1, ['slug' => 'new-slug'], 1);
    }


    public function testSubscribeMemberToPlanThrowsIfAlreadySubscribed(): void
    {
        $this->eligibilityService->shouldReceive('canMemberSubscribe')
            ->with(1, 1, 1)
            ->once()
            ->andReturn([
                'can_subscribe' => false,
                'reason' => 'Already subscribed to this plan'
            ]);

        $this->expectException(AlreadySubscribedException::class);
        $this->expectExceptionMessage('Already subscribed to this plan');

        $this->service->subscribeMemberToPlan(1, 1, 1);
    }

    public function testSubscribeMemberToPlanCreatesSubscription(): void
    {
        $subscription = Mockery::mock(Subscription::class);

        $this->eligibilityService->shouldReceive('canMemberSubscribe')
            ->with(1, 1, 1)
            ->once()
            ->andReturn(['can_subscribe' => true]);

        $this->subscriptionRepository->shouldReceive('createSubscription')
            ->with(1, 1, 1, [])
            ->once()
            ->andReturn($subscription);

        $result = $this->service->subscribeMemberToPlan(1, 1, 1);

        $this->assertSame($subscription, $result);
    }

    public function testCanMemberSubscribeDelegatesToEligibilityService(): void
    {
        $expected = [
            'can_subscribe' => true,
            'plan' => Mockery::mock(SubscriptionPlan::class)
        ];

        $this->eligibilityService->shouldReceive('canMemberSubscribe')
            ->with(1, 1, 1)
            ->once()
            ->andReturn($expected);

        $result = $this->service->canMemberSubscribe(1, 1, 1);

        $this->assertEquals($expected, $result);
    }

    public function testGetPlanWithStatsThrowsForNonexistent(): void
    {
        $this->planRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(PlanNotFoundException::class);
        $this->expectExceptionMessage('Plan with ID 999 not found');

        $this->service->getPlanWithStats(999);
    }


    public function testCanMemberSubscribeReturnsFalseForInactivePlan(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = false;

        $this->eligibilityService->shouldReceive('canMemberSubscribe')
            ->with(1, 1, 1)
            ->once()
            ->andReturn(['can_subscribe' => false, 'reason' => 'Plan not available']);

        $result = $this->service->canMemberSubscribe(1, 1, 1);

        $this->assertFalse($result['can_subscribe']);
        $this->assertEquals('Plan not available', $result['reason']);
    }

    public function testCanMemberSubscribeReturnsFalseIfAlreadySubscribedToSamePlan(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = true;

        $this->eligibilityService->shouldReceive('canMemberSubscribe')
            ->with(1, 1, 1)
            ->once()
            ->andReturn(['can_subscribe' => false, 'reason' => 'Already subscribed to this plan']);

        $result = $this->service->canMemberSubscribe(1, 1, 1);

        $this->assertFalse($result['can_subscribe']);
        $this->assertEquals('Already subscribed to this plan', $result['reason']);
    }

    public function testDeletePlanThrowsIfHasActiveSubscriptions(): void
    {
        $this->planRepository->shouldReceive('getSubscriberCount')
            ->with(1)
            ->once()
            ->andReturn(5);

        $this->expectException(PlanHasActiveSubscriptionsException::class);
        $this->expectExceptionMessage('Cannot delete plan with 5 active subscriptions');

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
        $plan->is_active = true;

        $subscription = Mockery::mock(Subscription::class);

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;

        $this->planRepository->shouldReceive('find')
            ->with($planId)
            ->once()
            ->andReturn($plan);

        $voucherValidationResult = new VoucherValidationResult(
            valid: true,
            discount: 2.99,
            message: 'Voucher applied successfully',
            voucher: $voucher
        );

        $this->voucherServiceMock->shouldReceive('validateVoucherForSubscription')
            ->with($voucherCode, $planId, $memberId)
            ->andReturn($voucherValidationResult);

        $this->subscriptionRepository->shouldReceive('createSubscription')
            ->with($memberId, $planId, $siteId, Mockery::on(function ($data) {
                return $data['voucher_id'] == 1
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
        $plan->is_active = true;

        $this->planRepository->shouldReceive('find')
            ->once()
            ->andReturn($plan);

        $voucherValidationResult = new VoucherValidationResult(
            valid: false,
            discount: 0,
            voucher: null,
            message: 'Voucher not found'
        );

        $this->voucherServiceMock->shouldReceive('validateVoucherForSubscription')
            ->andReturn($voucherValidationResult);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Voucher not found');

        $this->service->subscribeMemberToPlanWithVoucher(1, 1, 1, 'INVALID');
    }

    public function testSubscribeMemberToPlanWithVoucherCapsDiscountAtPrice(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $plan->id = 1;
        $plan->price = 29.99;
        $plan->is_active = true;

        $this->planRepository->shouldReceive('find')->andReturn($plan);

        $voucherValidationResult = new VoucherValidationResult(
            valid: true,
            discount: 50.00, // More than price
            voucher: $voucher,
            message: 'Discount capped at plan price'
        );

        $this->voucherServiceMock->shouldReceive('validateVoucherForSubscription')
            ->andReturn($voucherValidationResult);

        $subscription = Mockery::mock(Subscription::class);
        $this->subscriptionRepository->shouldReceive('createSubscription')
            ->with(1, 1, 1, Mockery::on(function ($data) {
                // Discount should be capped at price
                return abs($data['discount_amount'] - 29.99) < 0.01;
            }))
            ->andReturn($subscription);

        $this->service->subscribeMemberToPlanWithVoucher(1, 1, 1, 'BIGDISCOUNT');
        $this->assertTrue(true);
    }

    public function testSubscribeMemberToPlanWithVoucherThrowsForInactivePlan(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = false;

        $this->planRepository->shouldReceive('find')->andReturn($plan);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot subscribe to inactive plan');

        $this->service->subscribeMemberToPlanWithVoucher(1, 1, 1, 'CODE');
    }

    public function testSubscribeMemberToPlanWithoutVoucher(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->price = 29.99;
        $plan->is_active = true;

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

        $this->expectException(PlanNotFoundException::class);
        $this->expectExceptionMessage('Plan with ID 999 not found');

        $this->service->subscribeMemberToPlanWithVoucher(1, 999, 1, 'SUB10');
    }
}