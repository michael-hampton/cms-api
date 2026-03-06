<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Actions\Subscriptions\CreatePlanAction;
use App\DTO\Vouchers\VoucherValidationResult;
use App\Exceptions\Subscriptions\AlreadySubscribedException;
use App\Exceptions\Subscriptions\PlanHasActiveSubscriptionsException;
use App\Exceptions\Subscriptions\PlanNotFoundException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Voucher;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionEligibilityService;
use App\Services\Subscriptions\SubscriptionPlanPricingService;
use App\Services\Subscriptions\SubscriptionPlanService;
use App\Services\Vouchers\VoucherService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class SubscriptionPlanServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $planRepository;
    private $subscriptionRepository;
    private $service;
    private $voucherServiceMock;
    private $eligibilityService;
    private $createPlanAction;
    private $pricingService;
    private $databaseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->voucherServiceMock = Mockery::mock(VoucherService::class);
        $this->eligibilityService = Mockery::mock(SubscriptionEligibilityService::class);
        $this->createPlanAction = Mockery::mock(CreatePlanAction::class);
        $this->pricingService = Mockery::mock(SubscriptionPlanPricingService::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new SubscriptionPlanService(
            $this->planRepository,
            $this->subscriptionRepository,
            $this->voucherServiceMock,
            $this->eligibilityService,
            $this->createPlanAction,
            $this->pricingService,
            $this->databaseMock,
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

    /**
     * createPlan now delegates to CreatePlanAction.
     * The service is responsible for data preparation; the action owns Stripe.
     */
    public function testCreatePlanDelegatesToCreatePlanAction(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $this->createPlanAction
            ->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($prepared) {
                return $prepared['name'] === 'Premium Plan'
                    && abs($prepared['price'] - 29.99) < 0.01
                    && $prepared['currency'] === 'USD'
                    && $prepared['is_active'] === true
                    && isset($prepared['slug'])
                    && $prepared['billing_period'] === 'monthly';
            }))
            ->andReturn($plan);

        // price + currency + billing_period present → pricing tier is created
        $this->pricingService
            ->shouldReceive('createPricingTier')
            ->once()
            ->with(1, Mockery::on(function ($d) {
                return $d['price'] === 29.99
                    && $d['currency'] === 'usd'
                    && $d['interval'] === 'month'
                    && $d['is_default'] === true;
            }));

        $result = $this->service->createPlan([
            'name' => 'Premium Plan',
            'price' => 29.99,
            'currency' => 'usd',
            'billing_period' => 'monthly',
            'is_active' => '1',
        ], 1);

        $this->assertSame($plan, $result);
    }

    public function testCreatePlanSkipsPricingTierWhenNoPriceData(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $this->createPlanAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn($plan);

        $this->pricingService->shouldReceive('createPricingTier')->never();

        $this->service->createPlan(['name' => 'No Price Plan'], 1);
        $this->assertTrue(true);
    }

    public function testCreatePlanPreparesData(): void
    {
        $data = [
            'name' => 'Premium Plan',
            'price' => 29.99,
            'currency' => 'usd',
            'billing_period' => 'monthly',
            'is_active' => '1',
            'digital_download_url' => 'https://example.com/download',
            'print_shipping_required' => '1',
            'includes_insider' => '1',
            'is_upgrade_option' => '1',
            'upgrade_from_plan_id' => 5,
            'dispatch_days' => '3',
            'release_date' => '2025-01-01 10:00:00',
            'pre_release_enabled' => '1',
            'categories' => ['magazine', 'print'],
            'tags' => json_encode(['monthly', 'gift']),
            'premium_access' => [
                ['type' => 'newsletter', 'identifier' => 'insider'],
                ['type' => 'newsletter', 'identifier' => 'full'],
            ],
        ];

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $this->createPlanAction
            ->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($prepared) {
                return $prepared['name'] === 'Premium Plan'
                    && abs($prepared['price'] - 29.99) < 0.01
                    && $prepared['currency'] === 'USD'
                    && $prepared['is_active'] === true
                    && isset($prepared['slug'])
                    && $prepared['billing_period'] === 'monthly'
                    && $prepared['digital_download_url'] === 'https://example.com/download'
                    && $prepared['print_shipping_required'] === true
                    && $prepared['includes_insider'] === true
                    && $prepared['is_upgrade_option'] === true
                    && $prepared['upgrade_from_plan_id'] === 5
                    && $prepared['dispatch_days'] === 3
                    && $prepared['release_date'] === '2025-01-01 10:00:00'
                    && $prepared['pre_release_enabled'] === true
                    && is_array($prepared['categories'])
                    && in_array('magazine', $prepared['categories'], true)
                    && is_array($prepared['tags'])
                    && in_array('monthly', $prepared['tags'], true)
                    && is_array($prepared['premium_access'])
                    && count($prepared['premium_access']) === 2;
            }))
            ->andReturn($plan);

        // price + currency + billing_period present → pricing tier is created
        $this->pricingService
            ->shouldReceive('createPricingTier')
            ->once()
            ->with(1, Mockery::on(function ($d) {
                return $d['price'] === 29.99
                    && $d['currency'] === 'usd'
                    && $d['interval'] === 'month'
                    && $d['is_default'] === true;
            }));

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Trial days cannot be negative');

        $this->service->createPlan($data, 1);
    }

    public function testUpdatePlanThrowsWhenChangingSlugWithActiveSubscriptions(): void
    {
        $existingPlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $existingPlan->slug = 'old-slug';

        $this->planRepository->shouldReceive('find')->once()->andReturn($existingPlan);
        $this->planRepository->shouldReceive('getSubscriberCount')->once()->andReturn(3);

        $this->expectException(PlanHasActiveSubscriptionsException::class);

        $this->service->updatePlan(1, ['slug' => 'new-slug']);
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

    public function testGetAllPlansWithStatsUsesAggregatedSubscriberCounts(): void
    {
        $plan1 = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan1->id = 1;
        $plan1->price = 10.0;

        $plan2 = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan2->id = 2;
        $plan2->price = 20.0;

        $plansCollection = new Collection([$plan1, $plan2]);

        $this->planRepository
            ->shouldReceive('getAllForSite')
            ->once()
            ->with(1)
            ->andReturn($plansCollection);

        $this->planRepository
            ->shouldReceive('getSubscriberCountsForPlans')
            ->once()
            ->with([1, 2])
            ->andReturn([
                1 => 3,
                2 => 5,
            ]);

        $result = $this->service->getAllPlansWithStats(1);

        $this->assertCount(2, $result);
        $this->assertSame(3, $result[0]['subscriber_count']);
        $this->assertSame(5, $result[1]['subscriber_count']);
        $this->assertSame(30.0, $result[0]['revenue']);
        $this->assertSame(100.0, $result[1]['revenue']);
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

    public function testCreatePlanSyncsRegionSets(): void
    {
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        // Use a real plan so the pivot insert works
        $realPlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Region Plan',
            'slug' => 'region-plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $this->createPlanAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn($realPlan);

        $this->pricingService
            ->shouldReceive('createPricingTier')
            ->once();

        $result = $this->service->createPlan([
            'name' => 'Region Plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'region_set_ids' => [$regionSet1->id, $regionSet2->id],
        ], $this->siteId);

        $result->load(['regionSets']);
        $ids = $result->regionSets->pluck('id')->toArray();

        $this->assertCount(2, $ids);
        $this->assertContains($regionSet1->id, $ids);
        $this->assertContains($regionSet2->id, $ids);
    }

    public function testCreatePlanWithNoRegionSetIdsSkipsSync(): void
    {
        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $realPlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'No Region Plan',
            'slug' => 'no-region-plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $this->createPlanAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn($realPlan);

        $this->pricingService
            ->shouldReceive('createPricingTier')
            ->once();

        $result = $this->service->createPlan([
            'name' => 'No Region Plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
        ], $this->siteId);

        $result->load(['regionSets']);

        $this->assertCount(0, $result->regionSets);
    }

    public function testCreatePlanReplacesExistingRegionSetsOnSync(): void
    {
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            });

        $realPlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Sync Plan',
            'slug' => 'sync-plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        // Pre-assign regionSet1
        \App\Models\SubscriptionPlanRegionSet::create([
            'subscription_plan_id' => $realPlan->id,
            'region_set_id' => $regionSet1->id,
        ]);

        $this->createPlanAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn($realPlan);

        $this->pricingService
            ->shouldReceive('createPricingTier')
            ->once();

        // Sync with only regionSet2 — regionSet1 should be removed
        $result = $this->service->createPlan([
            'name' => 'Sync Plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'region_set_ids' => [$regionSet2->id],
        ], $this->siteId);

        $result->load(['regionSets']);
        $ids = $result->regionSets->pluck('id')->toArray();

        $this->assertCount(1, $ids);
        $this->assertContains($regionSet2->id, $ids);
        $this->assertNotContains($regionSet1->id, $ids);
    }

    public function testUpdatePlanSyncsRegionSets(): void
    {
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();

        $realPlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Update Region Plan',
            'slug' => 'update-region-plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $this->planRepository
            ->shouldReceive('find')
            ->with($realPlan->id)
            ->andReturn($realPlan);

        $this->planRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($realPlan);

        $this->service->updatePlan($realPlan->id, [
            'name' => 'Update Region Plan',
            'region_set_ids' => [$regionSet1->id, $regionSet2->id],
        ], $this->siteId);

        $realPlan->load(['regionSets']);
        $ids = $realPlan->regionSets->pluck('id')->toArray();

        $this->assertCount(2, $ids);
        $this->assertContains($regionSet1->id, $ids);
        $this->assertContains($regionSet2->id, $ids);
    }

    public function testUpdatePlanClearsRegionSetsWhenEmptyArrayPassed(): void
    {
        $regionSet1 = $this->createRegionSet();

        $realPlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Clear Region Plan',
            'slug' => 'clear-region-plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        \App\Models\SubscriptionPlanRegionSet::create([
            'subscription_plan_id' => $realPlan->id,
            'region_set_id' => $regionSet1->id,
        ]);

        $this->planRepository
            ->shouldReceive('find')
            ->with($realPlan->id)
            ->andReturn($realPlan);

        $this->planRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($realPlan);

        $this->service->updatePlan($realPlan->id, [
            'name' => 'Clear Region Plan',
            'region_set_ids' => [],
        ], $this->siteId);

        $realPlan->load(['regionSets']);

        $this->assertCount(0, $realPlan->regionSets);
    }

    public function testUpdatePlanSkipsSyncWhenNoRegionSetIdsKey(): void
    {
        $regionSet1 = $this->createRegionSet();

        $realPlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Skip Sync Plan',
            'slug' => 'skip-sync-plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        \App\Models\SubscriptionPlanRegionSet::create([
            'subscription_plan_id' => $realPlan->id,
            'region_set_id' => $regionSet1->id,
        ]);

        $this->planRepository
            ->shouldReceive('find')
            ->with($realPlan->id)
            ->andReturn($realPlan);

        $this->planRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($realPlan);

        // No region_set_ids key — existing assignments should be untouched
        $this->service->updatePlan($realPlan->id, [
            'name' => 'Skip Sync Plan',
        ], $this->siteId);

        $realPlan->load(['regionSets']);

        $this->assertCount(1, $realPlan->regionSets);
        $this->assertEquals($regionSet1->id, $realPlan->regionSets->first()->id);
    }

}