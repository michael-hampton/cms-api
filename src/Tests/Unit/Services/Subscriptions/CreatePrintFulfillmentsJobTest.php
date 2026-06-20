<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\WorkflowStageResult;
use App\Enums\Subscriptions\PrintRunStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Framework\Container;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\CreatePrintFulfillmentsJob;
use App\Models\IssueDelivery;
use App\Models\PrintRun;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Workflow\WorkflowRunRecorder;
use App\Services\Workflow\WorkflowRunRecorderFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class CreatePrintFulfillmentsJobTest extends FunctionalTestCase
{
    private $printRunRepository;
    private $issueDeliveryRepository;
    private $issuesDeliveredRepository;
    private $recorderFactory;
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->printRunRepository = Mockery::mock(PrintRunRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->issuesDeliveredRepository = Mockery::mock(IssuesDeliveredRepository::class);
        $this->recorderFactory = Mockery::mock(WorkflowRunRecorderFactory::class)->shouldIgnoreMissing();
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $container = Container::getInstance();
        $container->instance(PrintRunRepository::class, $this->printRunRepository);
        $container->instance(IssueDeliveryRepository::class, $this->issueDeliveryRepository);
        $container->instance(IssuesDeliveredRepository::class, $this->issuesDeliveredRepository);
        $container->instance(WorkflowRunRecorderFactory::class, $this->recorderFactory);
        $container->instance(Logger::class, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_builds_print_chunks_from_dispatched_fulfilments(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $printSubscription = $this->createSubscription(SubscriptionType::PRINTED->value);
        $digitalSubscription = $this->createSubscription(SubscriptionType::DIGITAL->value);

        $this->printRunRepository->shouldReceive('find')->with(1)->once()->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->once()->andReturn($issueDelivery);
        $this->issuesDeliveredRepository
            ->shouldReceive('getDispatchedSubscriptionIdsForIssue')
            ->with(5)
            ->once()
            ->andReturn([$printSubscription->id, $digitalSubscription->id]);
        $printRun->shouldReceive('markFulfilling')->with(1)->once();
        $printRun->shouldReceive('markBatching')->never();
        $printRun->shouldReceive('markFailed')->never();

        $this->runJob();
        $this->assertTrue(true);
    }

    public function test_handles_no_dispatchable_print_fulfilments(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $recorder = Mockery::mock(WorkflowRunRecorder::class);

        $this->printRunRepository->shouldReceive('find')->with(1)->once()->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->once()->andReturn($issueDelivery);
        $this->issuesDeliveredRepository
            ->shouldReceive('getDispatchedSubscriptionIdsForIssue')
            ->with(5)
            ->once()
            ->andReturn([]);
        $printRun->shouldReceive('markFulfilling')->with(0)->once();
        $printRun->shouldReceive('markBatching')->once();
        $recorder->shouldReceive('record')->with(Mockery::on(function ($result) {
            return $result instanceof WorkflowStageResult
                && ($result->summary['total_fulfilments'] ?? null) === 0;
        }))->once();
        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
            ->once()
            ->andReturn($recorder);

        $this->runJob();
        $this->assertTrue(true);
    }

    public function test_returns_when_print_run_is_missing(): void
    {
        $this->printRunRepository->shouldReceive('find')->with(1)->once()->andReturn(null);
        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->issuesDeliveredRepository->shouldNotReceive('getDispatchedSubscriptionIdsForIssue');

        $this->runJob();
        $this->assertTrue(true);
    }

    public function test_returns_when_print_run_is_cancelled(): void
    {
        $printRun = $this->makePrintRun(PrintRunStatus::CANCELLED);

        $this->printRunRepository->shouldReceive('find')->with(1)->once()->andReturn($printRun);
        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->issuesDeliveredRepository->shouldNotReceive('getDispatchedSubscriptionIdsForIssue');

        $this->runJob();
        $this->assertTrue(true);
    }

    public function test_marks_print_run_failed_when_issue_is_missing(): void
    {
        $printRun = $this->makePrintRun();
        $recorder = Mockery::mock(WorkflowRunRecorder::class);

        $this->printRunRepository->shouldReceive('find')->with(1)->once()->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->once()->andReturn(null);
        $this->issuesDeliveredRepository->shouldNotReceive('getDispatchedSubscriptionIdsForIssue');
        $printRun->shouldReceive('markFailed')->once();
        $recorder->shouldReceive('record')->once();
        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
            ->once()
            ->andReturn($recorder);

        $this->runJob();
        $this->assertTrue(true);
    }

    private function runJob(): void
    {
        $job = CreatePrintFulfillmentsJob::for(1, 5);
        $job->__wakeup();
        $job->handle();
    }

    private function makePrintRun(PrintRunStatus $status = PrintRunStatus::PENDING)
    {
        $printRun = Mockery::mock(PrintRun::class)->makePartial();
        $printRun->id = 1;
        $printRun->status = $status->value;
        $printRun->shouldReceive('isCancelled')->andReturn($status === PrintRunStatus::CANCELLED);

        return $printRun;
    }

    private function makeIssueDelivery()
    {
        $issueDelivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $issueDelivery->id = 5;

        return $issueDelivery;
    }

    private function createSubscription(string $deliveryType): Subscription
    {
        return Subscription::create([
            'plan_id' => 1,
            'member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'type' => 'paid',
            'delivery_type' => $deliveryType,
        ]);
    }
}
