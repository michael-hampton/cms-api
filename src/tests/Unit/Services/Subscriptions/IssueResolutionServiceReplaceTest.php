<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\DTO\Subscriptions\PolicyValidationResult;
use App\DTO\Subscriptions\ReplacementEligibilityResult;
use App\Enums\Subscriptions\DecisionSource;
use App\Enums\Subscriptions\ReplacementLimitScope;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Models\IssueDelivery;
use App\Models\Subscription;
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
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Covers the replace() path and, specifically, the ticket's reordering:
 * operational eligibility (FulfilmentReplacementEligibilityService) now
 * runs *after* the policy has granted the request, and only for REPLACE.
 */
class IssueResolutionServiceReplaceTest extends FunctionalTestCase
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

    private function makePolicyMock(int $id, bool $allowed, ?string $blockedReason = null): ReplacementPolicyInterface
    {
        $policy = Mockery::mock(ReplacementPolicyInterface::class);
        $policy->shouldReceive('id')->andReturn($id);
        $policy->shouldReceive('name')->andReturn('Mock Policy');
        $policy->shouldReceive('replacementLimitScope')->andReturn(ReplacementLimitScope::PER_SUBSCRIPTION);
        $policy->shouldReceive('extensionLimitScope')->andReturn(ReplacementLimitScope::PER_SUBSCRIPTION);
        $policy->shouldReceive('validate')->andReturn(PolicyValidationResult::valid());
        $policy->shouldReceive('evaluate')->andReturn(
            $allowed ? PolicyEvaluationResult::allowed() : PolicyEvaluationResult::denied($blockedReason ?? 'Denied.')
        );

        return $policy;
    }

    public function test_it_replaces_and_decrements_stock_when_policy_allows_it(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $subscription = $this->makeSubscription($plan);
        $issueDelivery = $this->makeIssueDelivery();
        $policy = $this->makePolicyMock(55, true);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);
        $resolutionRepository->shouldReceive('hasOpenResolution')->once()->andReturn(false);
        $resolutionRepository->shouldReceive('countForScope')->andReturn(0);

        $issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $issueDeliveryRepository->shouldReceive('find')->once()->with(100)->andReturn($issueDelivery);

        $policyResolver = Mockery::mock(ReplacementPolicyResolver::class);
        $policyResolver->shouldReceive('resolveForSubscription')->once()->with(1, 10)->andReturn($policy);

        $eligibilityService = Mockery::mock(FulfilmentReplacementEligibilityService::class);
        $eligibilityService->shouldReceive('canRequest')
            ->once()
            ->with(1, 100, 10)
            ->andReturn(ReplacementEligibilityResult::allowed());

        $stockRepository = Mockery::mock(IssueDeliveryStockRepository::class);
        $stockRepository->shouldReceive('decrementIfAvailable')->once()->with(100)->andReturn(true);

        $replacement = (object) ['id' => 999];
        $replacementService = Mockery::mock(FulfilmentReplacementService::class);
        $replacementService->shouldReceive('requestReplacement')
            ->once()
            ->with(1, 100, 'Damaged in transit', 7, 10)
            ->andReturn($replacement);

        $resolution = Mockery::mock(SubscriptionIssueResolution::class)->makePartial();

        $resolutionRepository->shouldReceive('createReplacementResolution')
            ->once()
            ->with(10, 1, 100, ReplacementResolution::REPLACE, 'Damaged in transit', DecisionSource::POLICY, 7, 55, 999, null, ['stock_decremented' => true])
            ->andReturn($resolution);

        $service = new IssueResolutionService(
            $subscriptionRepository,
            $eligibilityService,
            $replacementService,
            Mockery::mock(SubscriptionIssueExtensionService::class),
            $stockRepository,
            $issueDeliveryRepository,
            $resolutionRepository,
            $policyResolver
        );

        $result = $service->resolve(1, 100, ReplacementResolution::REPLACE, 'Damaged in transit', 7, 10, false);

        $this->assertSame('replace', $result->decision);
        $this->assertSame($replacement, $result->replacement);
        $this->assertSame($resolution, $result->resolution);
    }

    public function test_operational_eligibility_is_only_checked_after_policy_grants_the_request(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $subscription = $this->makeSubscription($plan);
        $issueDelivery = $this->makeIssueDelivery();
        $deniedPolicy = $this->makePolicyMock(55, false, 'This plan does not allow issue replacements.');

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);
        $resolutionRepository->shouldReceive('hasOpenResolution')->once()->andReturn(false);
        $resolutionRepository->shouldReceive('countForScope')->andReturn(0);

        $issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $issueDeliveryRepository->shouldReceive('find')->once()->with(100)->andReturn($issueDelivery);

        $policyResolver = Mockery::mock(ReplacementPolicyResolver::class);
        $policyResolver->shouldReceive('resolveForSubscription')->once()->andReturn($deniedPolicy);

        $eligibilityService = Mockery::mock(FulfilmentReplacementEligibilityService::class);
        $eligibilityService->shouldNotReceive('canRequest');

        $service = new IssueResolutionService(
            $subscriptionRepository,
            $eligibilityService,
            Mockery::mock(FulfilmentReplacementService::class),
            Mockery::mock(SubscriptionIssueExtensionService::class),
            Mockery::mock(IssueDeliveryStockRepository::class),
            $issueDeliveryRepository,
            $resolutionRepository,
            $policyResolver
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This plan does not allow issue replacements.');

        $service->resolve(1, 100, ReplacementResolution::REPLACE, 'Reason', 7, 10, false);
    }

    public function test_it_rejects_when_stock_is_unavailable_even_though_policy_allowed_it(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $subscription = $this->makeSubscription($plan);
        $issueDelivery = $this->makeIssueDelivery();
        $policy = $this->makePolicyMock(55, true);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);
        $resolutionRepository->shouldReceive('hasOpenResolution')->once()->andReturn(false);
        $resolutionRepository->shouldReceive('countForScope')->andReturn(0);
        $resolutionRepository->shouldNotReceive('createReplacementResolution');

        $issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $issueDeliveryRepository->shouldReceive('find')->once()->with(100)->andReturn($issueDelivery);

        $policyResolver = Mockery::mock(ReplacementPolicyResolver::class);
        $policyResolver->shouldReceive('resolveForSubscription')->once()->andReturn($policy);

        $eligibilityService = Mockery::mock(FulfilmentReplacementEligibilityService::class);
        $eligibilityService->shouldReceive('canRequest')->once()->andReturn(ReplacementEligibilityResult::allowed());

        $stockRepository = Mockery::mock(IssueDeliveryStockRepository::class);
        $stockRepository->shouldReceive('decrementIfAvailable')->once()->with(100)->andReturn(false);

        $service = new IssueResolutionService(
            $subscriptionRepository,
            $eligibilityService,
            Mockery::mock(FulfilmentReplacementService::class),
            Mockery::mock(SubscriptionIssueExtensionService::class),
            $stockRepository,
            $issueDeliveryRepository,
            $resolutionRepository,
            $policyResolver
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This issue has no stock available for replacement.');

        $service->resolve(1, 100, ReplacementResolution::REPLACE, 'Reason', 7, 10, false);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
