<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\ReplacementEligibilityResult;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Models\Subscription;
use App\Models\SubscriptionIssueFulfilment;
use App\Models\SubscriptionIssueResolution;
use App\Repositories\Subscriptions\IssueDeliveryStockRepository;
use App\Repositories\Subscriptions\SubscriptionIssueResolutionRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentReplacementEligibilityService;
use App\Services\Subscriptions\FulfilmentReplacementService;
use App\Services\Subscriptions\IssueResolutionService;
use App\Services\Subscriptions\SubscriptionIssueExtensionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class IssueResolutionServiceExtendTest extends TestCase
{
    public function test_it_extends_subscription_by_one_issue(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $fulfilment = Mockery::mock(SubscriptionIssueFulfilment::class)->makePartial();
        $fulfilment->id = 88;
        $fulfilment->issue_delivery_id = 200;
        $fulfilment->scheduled_for = null;

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $eligibilityService = Mockery::mock(FulfilmentReplacementEligibilityService::class);
        $replacementService = Mockery::mock(FulfilmentReplacementService::class);
        $extensionService = Mockery::mock(SubscriptionIssueExtensionService::class);
        $stockRepository = Mockery::mock(IssueDeliveryStockRepository::class);
        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);

        $eligibilityService->shouldReceive('canRequest')
            ->once()
            ->with(1, 100, 10)
            ->andReturn(ReplacementEligibilityResult::allowed());

        $resolutionRepository->shouldReceive('hasOpenResolution')
            ->once()
            ->with(1, 100)
            ->andReturn(false);

        $subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($subscription);

        $extensionService->shouldReceive('extendByOneIssue')
            ->once()
            ->with($subscription)
            ->andReturn($fulfilment);

        $resolution = Mockery::mock(SubscriptionIssueResolution::class)->makePartial();

        $resolutionRepository->shouldReceive('createReplacementResolution')
            ->once()
            ->with(10, 1, 100, ReplacementResolution::EXTEND, 'Weekly copy would arrive too late', true, 7, null, 88, Mockery::type('array'))
            ->andReturn($resolution);

        $service = new IssueResolutionService(
            $subscriptionRepository,
            $eligibilityService,
            $replacementService,
            $extensionService,
            $stockRepository,
            $resolutionRepository
        );

        $result = $service->resolve(1, 100, ReplacementResolution::EXTEND, 'Weekly copy would arrive too late', 7, 10, true);

        $this->assertSame('extend', $result->decision);
        $this->assertSame($fulfilment, $result->extension_fulfilment);
        $this->assertSame($resolution, $result->resolution);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
