<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Services\Subscriptions\SubscriptionFulfilmentPauseService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionFulfilmentPauseServiceTest extends TestCase
{
    private $fulfilmentRepository;
    private $issueDeliveryRepository;
    private $logger;
    private SubscriptionFulfilmentPauseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new SubscriptionFulfilmentPauseService(
            $this->fulfilmentRepository,
            $this->issueDeliveryRepository,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeSubscription(): object
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 42;
        $subscription->plan_id = 7;

        return $subscription;
    }

    private function makeIssue(int $id): object
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = $id;

        return $issue;
    }

    public function test_pause_moves_pending_fulfilments_to_paused(): void
    {
        $subscription = $this->makeSubscription();

        $this->fulfilmentRepository->shouldReceive('pausePendingForSubscription')
            ->once()
            ->with(42)
            ->andReturn(7);

        $result = $this->service->pause($subscription);

        $this->assertSame(7, $result);
    }

    public function test_resume_does_nothing_when_nothing_was_paused(): void
    {
        $subscription = $this->makeSubscription();

        $this->fulfilmentRepository->shouldReceive('countPausedForSubscription')->with(42)->andReturn(0);
        $this->issueDeliveryRepository->shouldReceive('findFutureIssuesForPlan')->never();
        $this->fulfilmentRepository->shouldReceive('supersedePausedForSubscription')->never();
        $this->fulfilmentRepository->shouldReceive('createFromSchedule')->never();

        $result = $this->service->resume($subscription);

        $this->assertSame(0, $result);
    }

    public function test_resume_replaces_paused_fulfilments_with_the_same_count_from_next_available_issues(): void
    {
        // 12-issue subscription, 5 delivered, 7 pending -> paused. On resume
        // we expect exactly 7 replacement fulfilments from the next 7
        // available plan issues.
        $subscription = $this->makeSubscription();

        $this->fulfilmentRepository->shouldReceive('countPausedForSubscription')->with(42)->andReturn(7);

        $issues = new Collection(array_map(fn (int $i) => $this->makeIssue($i), range(101, 107)));

        $this->issueDeliveryRepository->shouldReceive('findFutureIssuesForPlan')
            ->once()
            ->andReturn($issues);

        $this->fulfilmentRepository->shouldReceive('supersedePausedForSubscription')
            ->once()
            ->with(42)
            ->andReturn(7);

        $this->fulfilmentRepository->shouldReceive('createFromSchedule')
            ->times(7)
            ->with(42, Mockery::type(IssueDelivery::class));

        $result = $this->service->resume($subscription);

        $this->assertSame(7, $result);
    }

    public function test_resume_creates_fewer_replacements_when_schedule_is_short(): void
    {
        $subscription = $this->makeSubscription();

        $this->fulfilmentRepository->shouldReceive('countPausedForSubscription')->with(42)->andReturn(7);

        // Only 4 future issues actually available.
        $issues = new Collection(array_map(fn (int $i) => $this->makeIssue($i), range(201, 204)));

        $this->issueDeliveryRepository->shouldReceive('findFutureIssuesForPlan')->once()->andReturn($issues);

        $this->fulfilmentRepository->shouldReceive('supersedePausedForSubscription')->once()->with(42)->andReturn(7);

        $this->fulfilmentRepository->shouldReceive('createFromSchedule')
            ->times(4)
            ->with(42, Mockery::type(IssueDelivery::class));

        $result = $this->service->resume($subscription);

        $this->assertSame(4, $result);
    }
}
