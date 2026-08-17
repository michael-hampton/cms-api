<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\DTO\Subscriptions\PolicyValidationResult;
use App\Enums\Subscriptions\DecisionSource;
use App\Enums\Subscriptions\ReplacementLimitScope;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Models\SubscriptionIssueFulfilment;
use App\Models\SubscriptionIssueResolution;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssueDeliveryStockRepository;
use App\Repositories\Subscriptions\SubscriptionIssueResolutionRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Contracts\ReplacementPolicyInterface;
use App\Services\Subscriptions\FulfilmentReplacementEligibilityService;
use App\Services\Subscriptions\FulfilmentReplacementService;
use App\Services\Subscriptions\IssueResolutionService;
use App\Services\Subscriptions\ReplacementPolicyResolver;
use App\Services\Subscriptions\SubscriptionIssueExtensionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class IssueResolutionServiceExtendTest extends TestCase
{
    private function makeSubscription(SubscriptionPlan $plan, int $id = 1, int $memberId = 500): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $id;
        $subscription->member_id = $memberId;
        $subscription->shouldReceive('plan')->andReturn($plan);

        return $subscription;
    }

    private function makeIssueDelivery(int $id = 100): IssueDelivery
    {
        $issueDelivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $issueDelivery->id = $id;

        return $issueDelivery;
    }

    private function makeFulfilment(): SubscriptionIssueFulfilment
    {
        $fulfilment = Mockery::mock(SubscriptionIssueFulfilment::class)->makePartial();
        $fulfilment->id = 88;
        $fulfilment->issue_delivery_id = 200;
        $fulfilment->scheduled_for = null;

        return $fulfilment;
    }

    private function makePolicyMock(int $id, bool $allowed, ?string $blockedReason = null): ReplacementPolicyInterface
    {
        $policy = Mockery::mock(ReplacementPolicyInterface::class);
        $policy->shouldReceive('id')->andReturn($id);
        $policy->shouldReceive('name')->andReturn('Mock Policy');
        $policy->shouldReceive('replacementLimitScope')->andReturn(ReplacementLimitScope::PER_ISSUE);
        $policy->shouldReceive('extensionLimitScope')->andReturn(ReplacementLimitScope::PER_SUBSCRIPTION);
        $policy->shouldReceive('validate')->andReturn(PolicyValidationResult::valid());
        $policy->shouldReceive('evaluate')->andReturn(
            $allowed ? PolicyEvaluationResult::allowed() : PolicyEvaluationResult::denied($blockedReason ?? 'Denied.')
        );

        return $policy;
    }

    public function test_it_extends_subscription_by_one_issue_when_policy_allows_it(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $subscription = $this->makeSubscription($plan);
        $issueDelivery = $this->makeIssueDelivery();
        $fulfilment = $this->makeFulfilment();
        $policy = $this->makePolicyMock(55, true);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);
        $resolutionRepository->shouldReceive('hasOpenResolution')->once()->with(1, 100)->andReturn(false);
        $resolutionRepository->shouldReceive('countForScope')->andReturn(0);

        $issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $issueDeliveryRepository->shouldReceive('find')->once()->with(100)->andReturn($issueDelivery);

        $policyResolver = Mockery::mock(ReplacementPolicyResolver::class);
        $policyResolver->shouldReceive('resolveForSubscription')->once()->with(1, 10)->andReturn($policy);

        $extensionService = Mockery::mock(SubscriptionIssueExtensionService::class);
        $extensionService->shouldReceive('extendByOneIssue')->once()->with($subscription)->andReturn($fulfilment);

        $resolution = Mockery::mock(SubscriptionIssueResolution::class)->makePartial();

        $resolutionRepository->shouldReceive('createReplacementResolution')
            ->once()
            ->with(10, 1, 100, ReplacementResolution::EXTEND, 'Weekly copy would arrive too late', DecisionSource::POLICY, 7, 55, null, 88, Mockery::type('array'))
            ->andReturn($resolution);

        $service = new IssueResolutionService(
            $subscriptionRepository,
            Mockery::mock(FulfilmentReplacementEligibilityService::class),
            Mockery::mock(FulfilmentReplacementService::class),
            $extensionService,
            Mockery::mock(IssueDeliveryStockRepository::class),
            $issueDeliveryRepository,
            $resolutionRepository,
            $policyResolver,
            Mockery::mock(Logger::class)->shouldIgnoreMissing(),
        );

        $result = $service->resolve(1, 100, ReplacementResolution::EXTEND, 'Weekly copy would arrive too late', 7, 10, false);

        $this->assertSame('extend', $result->decision);
        $this->assertSame($fulfilment, $result->extension_fulfilment);
        $this->assertSame($resolution, $result->resolution);
    }

    public function test_it_records_business_override_via_goodwill_policy_when_policy_blocks_extension_but_agent_overrides(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $subscription = $this->makeSubscription($plan);
        $issueDelivery = $this->makeIssueDelivery();
        $fulfilment = $this->makeFulfilment();

        $deniedPolicy = $this->makePolicyMock(55, false, 'The extension limit for this plan has been reached.');
        $goodwillPolicy = $this->makePolicyMock(6, true);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);
        $resolutionRepository->shouldReceive('hasOpenResolution')->once()->andReturn(false);
        $resolutionRepository->shouldReceive('countForScope')->andReturn(0);

        $issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $issueDeliveryRepository->shouldReceive('find')->once()->with(100)->andReturn($issueDelivery);

        $policyResolver = Mockery::mock(ReplacementPolicyResolver::class);
        $policyResolver->shouldReceive('resolveForSubscription')->once()->with(1, 10)->andReturn($deniedPolicy);
        $policyResolver->shouldReceive('resolveGoodwill')->once()->with(10)->andReturn($goodwillPolicy);

        $extensionService = Mockery::mock(SubscriptionIssueExtensionService::class);
        $extensionService->shouldReceive('extendByOneIssue')->once()->with($subscription)->andReturn($fulfilment);

        $resolution = Mockery::mock(SubscriptionIssueResolution::class)->makePartial();

        $resolutionRepository->shouldReceive('createReplacementResolution')
            ->once()
            ->with(10, 1, 100, ReplacementResolution::EXTEND, 'Goodwill extension', DecisionSource::BUSINESS_OVERRIDE, 7, 6, null, 88, Mockery::type('array'))
            ->andReturn($resolution);

        $service = new IssueResolutionService(
            $subscriptionRepository,
            Mockery::mock(FulfilmentReplacementEligibilityService::class),
            Mockery::mock(FulfilmentReplacementService::class),
            $extensionService,
            Mockery::mock(IssueDeliveryStockRepository::class),
            $issueDeliveryRepository,
            $resolutionRepository,
            $policyResolver,
            Mockery::mock(Logger::class)->shouldIgnoreMissing(),
        );

        $result = $service->resolve(1, 100, ReplacementResolution::EXTEND, 'Goodwill extension', 7, 10, true);

        $this->assertSame('extend', $result->decision);
        $this->assertSame($resolution, $result->resolution);
    }

    public function test_it_rejects_extension_blocked_by_policy_without_override(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $subscription = $this->makeSubscription($plan);
        $issueDelivery = $this->makeIssueDelivery();
        $deniedPolicy = $this->makePolicyMock(55, false, 'This plan does not allow subscription extensions.');

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);
        $resolutionRepository->shouldReceive('hasOpenResolution')->once()->andReturn(false);
        $resolutionRepository->shouldReceive('countForScope')->andReturn(0);
        $resolutionRepository->shouldNotReceive('createReplacementResolution');

        $issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $issueDeliveryRepository->shouldReceive('find')->once()->with(100)->andReturn($issueDelivery);

        $policyResolver = Mockery::mock(ReplacementPolicyResolver::class);
        $policyResolver->shouldReceive('resolveForSubscription')->once()->andReturn($deniedPolicy);
        $policyResolver->shouldNotReceive('resolveGoodwill');

        $service = new IssueResolutionService(
            $subscriptionRepository,
            Mockery::mock(FulfilmentReplacementEligibilityService::class),
            Mockery::mock(FulfilmentReplacementService::class),
            Mockery::mock(SubscriptionIssueExtensionService::class),
            Mockery::mock(IssueDeliveryStockRepository::class),
            $issueDeliveryRepository,
            $resolutionRepository,
            $policyResolver,
            Mockery::mock(Logger::class)->shouldIgnoreMissing(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This plan does not allow subscription extensions.');

        $service->resolve(1, 100, ReplacementResolution::EXTEND, 'Reason', 7, 10, false);
    }

    public function test_it_throws_when_subscription_plan_cannot_be_resolved(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->shouldReceive('plan')->andReturn(null);

        $issueDelivery = $this->makeIssueDelivery();

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);
        $resolutionRepository->shouldReceive('hasOpenResolution')->once()->andReturn(false);

        $issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $issueDeliveryRepository->shouldReceive('find')->once()->with(100)->andReturn($issueDelivery);

        $service = new IssueResolutionService(
            $subscriptionRepository,
            Mockery::mock(FulfilmentReplacementEligibilityService::class),
            Mockery::mock(FulfilmentReplacementService::class),
            Mockery::mock(SubscriptionIssueExtensionService::class),
            Mockery::mock(IssueDeliveryStockRepository::class),
            $issueDeliveryRepository,
            $resolutionRepository,
            Mockery::mock(ReplacementPolicyResolver::class),
            Mockery::mock(Logger::class)->shouldIgnoreMissing(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription plan not found.');

        $service->resolve(1, 100, ReplacementResolution::EXTEND, 'Reason', 7, 10, false);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
