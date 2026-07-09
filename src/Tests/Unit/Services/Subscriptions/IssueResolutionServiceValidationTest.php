<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\ReplacementResolution;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssueDeliveryStockRepository;
use App\Repositories\Subscriptions\SubscriptionIssueResolutionRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentReplacementEligibilityService;
use App\Services\Subscriptions\FulfilmentReplacementService;
use App\Services\Subscriptions\IssueResolutionService;
use App\Services\Subscriptions\ReplacementPolicyResolver;
use App\Services\Subscriptions\SubscriptionIssueExtensionService;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * NOTE on ordering: the new flow resolves the subscription and checks for
 * an open resolution before touching eligibility/policy at all (policy
 * resolution needs the subscription's plan_id; the duplicate guard is
 * cheap and universal to both decision types), so these validation
 * failures now short-circuit earlier than in the old
 * FulfilmentReplacementEligibilityService-first flow.
 */
class IssueResolutionServiceValidationTest extends TestCase
{
    public function test_it_rejects_blank_reason_before_touching_any_collaborator(): void
    {
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldNotReceive('find');

        $service = $this->makeService($subscriptionRepository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reason is required.');

        $service->resolve(1, 100, ReplacementResolution::EXTEND, '   ', 7, 10, false);
    }

    public function test_it_rejects_when_subscription_is_not_found(): void
    {
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn(null);

        $service = $this->makeService($subscriptionRepository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription not found.');

        $service->resolve(1, 100, ReplacementResolution::EXTEND, 'Reason', 7, 10, false);
    }

    public function test_it_rejects_duplicate_resolution(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);
        $resolutionRepository->shouldReceive('hasOpenResolution')->once()->with(1, 100)->andReturn(true);

        $issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $issueDeliveryRepository->shouldNotReceive('find');

        $service = $this->makeService($subscriptionRepository, $resolutionRepository, $issueDeliveryRepository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A resolution is already recorded for this issue.');

        $service->resolve(1, 100, ReplacementResolution::EXTEND, 'Reason', 7, 10, false);
    }

    public function test_it_rejects_when_the_issue_delivery_cannot_be_found(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);
        $resolutionRepository->shouldReceive('hasOpenResolution')->once()->andReturn(false);

        $issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $issueDeliveryRepository->shouldReceive('find')->once()->with(100)->andReturn(null);

        $service = $this->makeService($subscriptionRepository, $resolutionRepository, $issueDeliveryRepository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Issue not found.');

        $service->resolve(1, 100, ReplacementResolution::EXTEND, 'Reason', 7, 10, false);
    }

    private function makeService(
        SubscriptionRepository $subscriptionRepository,
        ?SubscriptionIssueResolutionRepository $resolutionRepository = null,
        ?IssueDeliveryRepository $issueDeliveryRepository = null
    ): IssueResolutionService {
        return new IssueResolutionService(
            $subscriptionRepository,
            Mockery::mock(FulfilmentReplacementEligibilityService::class),
            Mockery::mock(FulfilmentReplacementService::class),
            Mockery::mock(SubscriptionIssueExtensionService::class),
            Mockery::mock(IssueDeliveryStockRepository::class),
            $issueDeliveryRepository ?: Mockery::mock(IssueDeliveryRepository::class),
            $resolutionRepository ?: Mockery::mock(SubscriptionIssueResolutionRepository::class),
            Mockery::mock(ReplacementPolicyResolver::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
