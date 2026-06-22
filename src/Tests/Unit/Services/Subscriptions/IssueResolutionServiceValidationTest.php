<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\ReplacementEligibilityResult;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Repositories\Subscriptions\IssueDeliveryStockRepository;
use App\Repositories\Subscriptions\SubscriptionIssueResolutionRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentReplacementEligibilityService;
use App\Services\Subscriptions\FulfilmentReplacementService;
use App\Services\Subscriptions\IssueResolutionService;
use App\Services\Subscriptions\SubscriptionIssueExtensionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class IssueResolutionServiceValidationTest extends TestCase
{
    public function test_it_rejects_denied_eligibility(): void
    {
        $eligibilityService = Mockery::mock(FulfilmentReplacementEligibilityService::class);
        $eligibilityService->shouldReceive('canRequest')
            ->once()
            ->with(1, 100, 10)
            ->andReturn(ReplacementEligibilityResult::denied('Only dispatched issues can be replaced.'));

        $service = $this->makeService($eligibilityService);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only dispatched issues can be replaced.');

        $service->resolve(1, 100, ReplacementResolution::EXTEND, 'Reason', 7, 10, false);
    }

    public function test_it_rejects_duplicate_resolution(): void
    {
        $eligibilityService = Mockery::mock(FulfilmentReplacementEligibilityService::class);
        $resolutionRepository = Mockery::mock(SubscriptionIssueResolutionRepository::class);

        $eligibilityService->shouldReceive('canRequest')
            ->once()
            ->andReturn(ReplacementEligibilityResult::allowed());

        $resolutionRepository->shouldReceive('hasOpenResolution')
            ->once()
            ->with(1, 100)
            ->andReturn(true);

        $service = $this->makeService($eligibilityService, $resolutionRepository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A resolution is already recorded for this issue.');

        $service->resolve(1, 100, ReplacementResolution::EXTEND, 'Reason', 7, 10, false);
    }

    public function test_it_rejects_blank_reason(): void
    {
        $eligibilityService = Mockery::mock(FulfilmentReplacementEligibilityService::class);
        $eligibilityService->shouldNotReceive('canRequest');

        $service = $this->makeService($eligibilityService);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reason is required.');

        $service->resolve(1, 100, ReplacementResolution::EXTEND, '   ', 7, 10, false);
    }

    private function makeService(
        FulfilmentReplacementEligibilityService $eligibilityService,
        ?SubscriptionIssueResolutionRepository $resolutionRepository = null
    ): IssueResolutionService {
        return new IssueResolutionService(
            Mockery::mock(SubscriptionRepository::class),
            $eligibilityService,
            Mockery::mock(FulfilmentReplacementService::class),
            Mockery::mock(SubscriptionIssueExtensionService::class),
            Mockery::mock(IssueDeliveryStockRepository::class),
            $resolutionRepository ?: Mockery::mock(SubscriptionIssueResolutionRepository::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
