<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\GenerateIssueDeliveriesJob;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Services\Subscriptions\IssueDeliveryEligibilityService;
use App\Services\Subscriptions\IssueFulfilmentDispatchCoordinator;
use App\Services\Subscriptions\IssueFulfilmentPlanner;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use DomainException;
use Mockery;

class GenerateIssueDeliveriesJobTest extends FunctionalTestCase
{
    private $issueDeliveryRepository;
    private $eligibilityService;
    private $fulfilmentPlanner;
    private $dispatchCoordinator;
    private $databaseMock;
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->eligibilityService = Mockery::mock(IssueDeliveryEligibilityService::class);
        $this->fulfilmentPlanner = Mockery::mock(IssueFulfilmentPlanner::class);
        $this->dispatchCoordinator = Mockery::mock(IssueFulfilmentDispatchCoordinator::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $container = Container::getInstance();
        $container->instance(IssueDeliveryRepository::class, $this->issueDeliveryRepository);
        $container->instance(IssueDeliveryEligibilityService::class, $this->eligibilityService);
        $container->instance(IssueFulfilmentPlanner::class, $this->fulfilmentPlanner);
        $container->instance(IssueFulfilmentDispatchCoordinator::class, $this->dispatchCoordinator);
        $container->instance(Database::class, $this->databaseMock);
        $container->instance(Logger::class, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_plans_and_dispatches_eligible_subscriptions(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::ACTIVE);
        $subscriptions = collect([new \stdClass(), new \stdClass()]);
        $plan = [
            'digital_ids' => [10],
            'print_ids' => [11],
            'created' => 2,
            'deferred' => 0,
        ];
        $dispatchSummary = [
            'issue_delivery_id' => 25,
            'created' => 2,
            'deferred' => 0,
            'digital_dispatches' => 1,
            'print_dispatches' => 1,
        ];

        $this->issueDeliveryRepository->shouldReceive('find')->with(25)->once()->andReturn($issueDelivery);
        $this->eligibilityService
            ->shouldReceive('getEligibleSubscriptions')
            ->with($issueDelivery)
            ->once()
            ->andReturn($subscriptions);
        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });
        $this->fulfilmentPlanner
            ->shouldReceive('plan')
            ->with($issueDelivery, $subscriptions)
            ->once()
            ->andReturn($plan);
        $this->dispatchCoordinator
            ->shouldReceive('dispatch')
            ->with($issueDelivery, $plan)
            ->once()
            ->andReturn($dispatchSummary);

        $result = $this->runJob(25);

        $this->assertEquals(2, $result['eligible_subscriptions']);
        $this->assertEquals(1, $result['digital_dispatches']);
        $this->assertEquals(1, $result['print_dispatches']);
    }

    public function test_empty_eligibility_still_runs_planner_and_coordinator(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::ACTIVE);
        $subscriptions = collect([]);
        $plan = [
            'digital_ids' => [],
            'print_ids' => [],
            'created' => 0,
            'deferred' => 0,
        ];

        $this->issueDeliveryRepository->shouldReceive('find')->with(25)->once()->andReturn($issueDelivery);
        $this->eligibilityService->shouldReceive('getEligibleSubscriptions')->once()->andReturn($subscriptions);
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });
        $this->fulfilmentPlanner->shouldReceive('plan')->with($issueDelivery, $subscriptions)->once()->andReturn($plan);
        $this->dispatchCoordinator->shouldReceive('dispatch')->with($issueDelivery, $plan)->once()->andReturn([
            'issue_delivery_id' => 25,
            'created' => 0,
            'deferred' => 0,
            'digital_dispatches' => 0,
            'print_dispatches' => 0,
        ]);

        $result = $this->runJob(25);

        $this->assertEquals(0, $result['eligible_subscriptions']);
    }

    public function test_replans_dispatched_issue_delivery_idempotently(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::DISPATCHED);
        $subscriptions = collect([]);
        $plan = [
            'digital_ids' => [],
            'print_ids' => [],
            'created' => 0,
            'deferred' => 0,
            'already_dispatched' => 2,
        ];

        $this->issueDeliveryRepository->shouldReceive('find')->with(25)->once()->andReturn($issueDelivery);
        $this->eligibilityService->shouldReceive('getEligibleSubscriptions')->once()->andReturn($subscriptions);
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(
            static fn ($callback) => $callback()
        );
        $this->fulfilmentPlanner->shouldReceive('plan')->with($issueDelivery, $subscriptions)->once()->andReturn($plan);
        $this->dispatchCoordinator->shouldReceive('dispatch')->with($issueDelivery, $plan)->once()->andReturn([
            'issue_delivery_id' => 25,
            'created' => 0,
            'deferred' => 0,
            'already_dispatched' => 2,
            'digital_dispatches' => 0,
            'print_dispatches' => 0,
        ]);

        $result = $this->runJob(25);

        $this->assertSame(2, $result['already_dispatched']);
    }

    public function test_skips_cancelled_issue_delivery(): void
    {
        $this->assertIssueIsSkipped(IssueDeliveryStatus::CANCELLED);
    }

    public function test_marks_issue_failed_when_eligibility_resolution_fails(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::ACTIVE);
        $message = 'subscription plan has no associated newsletter';

        $this->issueDeliveryRepository->shouldReceive('find')->with(25)->once()->andReturn($issueDelivery);
        $this->eligibilityService
            ->shouldReceive('getEligibleSubscriptions')
            ->with($issueDelivery)
            ->once()
            ->andThrow(new DomainException($message));
        $issueDelivery->shouldReceive('markDispatchFailed')->with($message)->once();
        $this->fulfilmentPlanner->shouldNotReceive('plan');
        $this->dispatchCoordinator->shouldNotReceive('dispatch');

        $this->assertSame([], $this->runJob(25));
    }

    private function assertIssueIsSkipped(IssueDeliveryStatus $status): void
    {
        $issueDelivery = $this->makeIssueDelivery($status);

        $this->issueDeliveryRepository->shouldReceive('find')->with(25)->once()->andReturn($issueDelivery);
        $this->eligibilityService->shouldNotReceive('getEligibleSubscriptions');
        $this->fulfilmentPlanner->shouldNotReceive('plan');
        $this->dispatchCoordinator->shouldNotReceive('dispatch');

        $this->assertSame([], $this->runJob(25));
    }

    private function runJob(int $issueDeliveryId): array
    {
        $job = GenerateIssueDeliveriesJob::for($issueDeliveryId);
        $job->__wakeup();

        return $job->handle();
    }

    private function makeIssueDelivery(IssueDeliveryStatus $status): IssueDelivery
    {
        $issueDelivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $issueDelivery->id = 25;
        $issueDelivery->status = $status->value;
        $issueDelivery->shouldReceive('isActive')->andReturn($status === IssueDeliveryStatus::ACTIVE);

        return $issueDelivery;
    }
}
