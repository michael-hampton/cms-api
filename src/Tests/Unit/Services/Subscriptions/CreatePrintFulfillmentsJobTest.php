<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\WorkflowStageResult;
use App\Enums\Subscriptions\PrintRunStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Framework\Container;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\Job;
use App\Framework\Queue\QueueDriverInterface;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\CreateFulfilmentsChunkJob;
use App\Jobs\Subscriptions\CreatePrintFulfillmentsJob;
use App\Jobs\Subscriptions\FulfilmentCompletionMonitorJob;
use App\Models\IssueDelivery;
use App\Models\PrintRun;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Workflow\WorkflowRunRecorder;
use App\Services\Workflow\WorkflowRunRecorderFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class CreatePrintFulfillmentsJobTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $printRunRepository;
    private $issueDeliveryRepository;
    private $subscriptionIssueFulfilmentRepository;
    private $recorderFactory;
    private $logger;
    private int $planId;
    private array $queuedJobs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->planId = (int) $this->createSubscriptionPlan()->id;

        $this->printRunRepository = Mockery::mock(PrintRunRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->subscriptionIssueFulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->recorderFactory = Mockery::mock(WorkflowRunRecorderFactory::class)->shouldIgnoreMissing();
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $queueDriver = Mockery::mock(QueueDriverInterface::class);
        $queueDriver->shouldReceive('push')->andReturnUsing(function (Job $job) {
            $this->queuedJobs[] = $job;
        });

        $container = Container::getInstance();
        $container->instance(PrintRunRepository::class, $this->printRunRepository);
        $container->instance(IssueDeliveryRepository::class, $this->issueDeliveryRepository);
        $container->instance(SubscriptionIssueFulfilmentRepository::class, $this->subscriptionIssueFulfilmentRepository);
        $container->instance(WorkflowRunRecorderFactory::class, $this->recorderFactory);
        $container->instance(Logger::class, $this->logger);
        $container->instance(Dispatcher::class, new Dispatcher($queueDriver));
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

        $this->expectRepositories($printRun, $issueDelivery, [
            $printSubscription->id,
            $digitalSubscription->id,
        ]);
        $printRun->shouldReceive('markFulfilling')->with(1)->once();
        $printRun->shouldReceive('markBatching')->never();
        $printRun->shouldReceive('markFailed')->never();

        $this->runJob();

        $chunks = $this->queuedJobs(CreateFulfilmentsChunkJob::class);
        $monitors = $this->queuedJobs(FulfilmentCompletionMonitorJob::class);

        $this->assertCount(1, $chunks);
        $this->assertCount(1, $monitors);
        $this->assertSame([$printSubscription->id], $chunks[0]->subscriptionIds());
        $this->assertSame(0, $chunks[0]->chunkIndex());
        $this->assertSame('print', $chunks[0]->queue);
        $this->assertSame('print', $monitors[0]->queue);
    }

    public function test_dispatches_exact_ids_in_deterministic_chunks_for_large_sets(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $subscriptionIds = [];

        for ($index = 0; $index < 450; $index++) {
            $subscriptionIds[] = $this->createSubscription(SubscriptionType::PRINTED->value)->id;
        }

        $this->expectRepositories($printRun, $issueDelivery, array_reverse($subscriptionIds));
        $printRun->shouldReceive('markFulfilling')->with(3)->once();
        $printRun->shouldReceive('markBatching')->never();

        $this->runJob();

        $chunks = $this->queuedJobs(CreateFulfilmentsChunkJob::class);
        $queuedIds = [];

        foreach ($chunks as $chunk) {
            $queuedIds = array_merge($queuedIds, $chunk->subscriptionIds());
        }

        sort($subscriptionIds);

        $this->assertCount(3, $chunks);
        $this->assertSame($subscriptionIds, $queuedIds);
        $this->assertCount(200, $chunks[0]->subscriptionIds());
        $this->assertCount(200, $chunks[1]->subscriptionIds());
        $this->assertCount(50, $chunks[2]->subscriptionIds());
        $this->assertSame([0, 1, 2], array_map(function ($chunk) {
            return $chunk->chunkIndex();
        }, $chunks));
        $this->assertCount(1, $this->queuedJobs(FulfilmentCompletionMonitorJob::class));
    }

    public function test_handles_no_dispatchable_print_fulfilments(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $recorder = Mockery::mock(WorkflowRunRecorder::class);

        $this->expectRepositories($printRun, $issueDelivery, []);
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

        $this->assertSame([], $this->queuedJobs);
    }

    public function test_returns_when_print_run_is_missing(): void
    {
        $this->printRunRepository->shouldReceive('find')->with(1)->once()->andReturn(null);
        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->subscriptionIssueFulfilmentRepository->shouldNotReceive('getDispatchedSubscriptionIdsForIssue');

        $this->runJob();

        $this->assertSame([], $this->queuedJobs);
    }

    public function test_returns_when_print_run_is_cancelled(): void
    {
        $printRun = $this->makePrintRun(PrintRunStatus::CANCELLED);

        $this->printRunRepository->shouldReceive('find')->with(1)->once()->andReturn($printRun);
        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->subscriptionIssueFulfilmentRepository->shouldNotReceive('getDispatchedSubscriptionIdsForIssue');

        $this->runJob();

        $this->assertSame([], $this->queuedJobs);
    }

    public function test_marks_print_run_failed_when_issue_is_missing(): void
    {
        $printRun = $this->makePrintRun();
        $recorder = Mockery::mock(WorkflowRunRecorder::class);

        $this->printRunRepository->shouldReceive('find')->with(1)->once()->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->once()->andReturn(null);
        $this->subscriptionIssueFulfilmentRepository->shouldNotReceive('getDispatchedSubscriptionIdsForIssue');
        $printRun->shouldReceive('markFailed')->once();
        $recorder->shouldReceive('record')->once();
        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
            ->once()
            ->andReturn($recorder);

        $this->runJob();

        $this->assertSame([], $this->queuedJobs);
    }

    private function expectRepositories(PrintRun $printRun, IssueDelivery $issueDelivery, array $subscriptionIds): void
    {
        $this->printRunRepository->shouldReceive('find')->with(1)->once()->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->once()->andReturn($issueDelivery);
        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('getDispatchedSubscriptionIdsForIssue')
            ->with(5)
            ->once()
            ->andReturn($subscriptionIds);
    }

    private function queuedJobs(string $class): array
    {
        return array_values(array_filter($this->queuedJobs, function ($job) use ($class) {
            return $job instanceof $class;
        }));
    }

    private function runJob(): void
    {
        $job = CreatePrintFulfillmentsJob::for(1, 5);
        $job->__wakeup();
        $job->handle();
    }

    private function makePrintRun(PrintRunStatus $status = PrintRunStatus::PENDING): PrintRun
    {
        $printRun = Mockery::mock(PrintRun::class)->makePartial();
        $printRun->id = 1;
        $printRun->status = $status->value;
        $printRun->shouldReceive('isCancelled')->andReturn($status === PrintRunStatus::CANCELLED);
        return $printRun;
    }

    private function makeIssueDelivery(): IssueDelivery
    {
        $issueDelivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $issueDelivery->id = 5;
        return $issueDelivery;
    }

    private function createSubscription(string $deliveryType): Subscription
    {
        return Subscription::create([
            'plan_id' => $this->planId,
            'member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'type' => 'paid',
            'delivery_type' => $deliveryType,
            'start_date' => date('Y-m-d H:i:s'),
        ]);
    }
}
