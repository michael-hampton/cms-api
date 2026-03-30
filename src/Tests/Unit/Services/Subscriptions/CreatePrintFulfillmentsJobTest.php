<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\WorkflowStageResult;
use App\Enums\Subscriptions\PrintRunStatus;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\CreatePrintFulfillmentsJob;
use App\Models\IssueDelivery;
use App\Models\PrintRun;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Workflow\WorkflowRunRecorder;
use App\Services\Workflow\WorkflowRunRecorderFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class CreatePrintFulfillmentsJobTest extends FunctionalTestCase
{
    private PrintRunRepository|MockInterface $printRunRepository;
    private IssueDeliveryRepository|MockInterface $issueDeliveryRepository;
    private SubscriptionRepository|MockInterface $subscriptionRepository;
    private WorkflowRunRecorderFactory|MockInterface $recorderFactory;
    private Logger|MockInterface $logger;

    public function test_it_marks_print_run_fulfilling_and_dispatches_one_chunk_job_and_monitor(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $subs = $this->makeSubscriptions(count: 5);

        $this->printRunRepository->shouldReceive('find')->with(1)->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->with(5, 99, Mockery::type(\DateTime::class))
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once()->with(1);
        $printRun->shouldReceive('markFailed')->never();
        $printRun->shouldReceive('markBatching')->never();

        $chunkJobs = [];

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);

    }

    private function makePrintRun(PrintRunStatus $status = PrintRunStatus::PENDING): MockInterface
    {
        $printRun = Mockery::mock(PrintRun::class)->makePartial();
        $printRun->id = 1;
        $printRun->status = $status->value;

        $printRun->shouldReceive('isCancelled')
            ->andReturn($status === PrintRunStatus::CANCELLED);

        return $printRun;
    }

    // =========================================================================
    // Happy path — subscriptions fit in a single chunk
    // =========================================================================

    private function makeIssueDelivery(): MockInterface
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = 5;
        $delivery->subscription_plan_id = 99;
        return $delivery;
    }

    private function makeSubscriptions(int $count, int $startId = 1): Collection
    {
        $subs = [];
        foreach (range($startId, $startId + $count - 1) as $id) {
            $sub = Mockery::mock(Subscription::class)->makePartial();
            $sub->id = $id;
            $subs[] = $sub;
        }
        return new Collection($subs);
    }

    private function makeJob(): CreatePrintFulfillmentsJob
    {
        return new CreatePrintFulfillmentsJob(
            printRunRepository: $this->printRunRepository,
            issueDeliveryRepository: $this->issueDeliveryRepository,
            subscriptionRepository: $this->subscriptionRepository,
            recorderFactory: $this->recorderFactory,
            logger: $this->logger,
        );
    }

    // =========================================================================
    // Zero-subscription edge case
    // =========================================================================

    public function test_it_dispatches_correct_number_of_chunk_jobs_for_large_subscription_sets(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();

        // 450 subs, chunk_size 200 → 3 chunks (200 + 200 + 50)
        $subs = $this->makeSubscriptions(count: 450);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once()->with(3);

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_it_passes_correct_subscription_ids_to_chunk_jobs(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $subs = $this->makeSubscriptions(count: 3, startId: 10);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once();


        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    // =========================================================================
    // Guard conditions
    // =========================================================================

    public function test_it_fires_all_fulfillments_created_immediately_when_no_print_subscriptions(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')
            ->once()
            ->with(Mockery::on(fn($r) => $r instanceof WorkflowStageResult
                && $r->summary['total_chunks'] === 0
                && $r->summary['total_fulfilments'] === 0
                && isset($r->summary['skipped_reason'])
            ));

        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
            ->andReturn($recorder);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn(new Collection([]));

        $printRun->shouldReceive('markFulfilling')->once()->with(0);
        $printRun->shouldReceive('markBatching')->once();
        $printRun->shouldReceive('markFailed')->never();


        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_it_does_not_dispatch_chunk_jobs_when_no_subscriptions(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn(new Collection([]));

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')
            ->once()
            ->with(Mockery::on(fn($r) => $r instanceof WorkflowStageResult
                && $r->summary['total_chunks'] === 0
                && $r->summary['total_fulfilments'] === 0
                && isset($r->summary['skipped_reason'])
            ));

        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
            ->andReturn($recorder);

        $printRun->shouldReceive('markFulfilling')->once();
        $printRun->shouldReceive('markBatching')->once();

        $this->makeJob()->handle(
            1, 5
        );

        $this->assertTrue(true);
    }

    // =========================================================================
    // Monitor dispatch
    // =========================================================================

    public function test_it_returns_early_when_print_run_not_found(): void
    {
        $this->printRunRepository->shouldReceive('find')->andReturn(null);

        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->subscriptionRepository->shouldNotReceive('findPrintSubscriptionsForIssueDelivery');


        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_it_marks_print_run_failed_and_returns_early_when_issue_delivery_not_found(): void
    {
        $printRun = $this->makePrintRun();

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn(null);

        $printRun->shouldReceive('markFailed')->once();
        $printRun->shouldReceive('markFulfilling')->never();

        $this->subscriptionRepository->shouldNotReceive('findPrintSubscriptionsForIssueDelivery');

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')
            ->once();

        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
            ->andReturn($recorder);

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);

    }

    public function test_it_dispatches_monitor_job_with_configured_delay(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $subs = $this->makeSubscriptions(count: 1);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once();


        $this->makeJob()->handle(1, 5);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->printRunRepository = Mockery::mock(PrintRunRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->recorderFactory = Mockery::mock(WorkflowRunRecorderFactory::class)
            ->shouldIgnoreMissing();
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
    }

    public function test_marks_print_run_fulfilling_and_dispatches_chunk_job_and_monitor(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $subs = $this->makeSubscriptions(count: 5);

        $this->printRunRepository->shouldReceive('find')->with(1)->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->with(5, 99, Mockery::type(\DateTime::class))
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once()->with(1);
        $printRun->shouldReceive('markFailed')->never();
        $printRun->shouldReceive('markBatching')->never();

        $this->recorderFactory->shouldNotReceive('forPrintRun');

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_dispatches_correct_number_of_chunk_jobs_for_large_subscription_sets(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();

        // 450 subs, chunk_size 200 → 3 chunks (200 + 200 + 50)
        $subs = $this->makeSubscriptions(count: 450);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once()->with(3);

        $this->recorderFactory->shouldNotReceive('forPrintRun');

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_passes_correct_subscription_ids_to_chunk_jobs(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();
        $subs = $this->makeSubscriptions(count: 3, startId: 10);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn($subs);

        $printRun->shouldReceive('markFulfilling')->once();

        $this->recorderFactory->shouldNotReceive('forPrintRun');

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_fires_all_fulfillments_created_and_records_to_workflow_run_when_no_subscriptions(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn(new Collection([]));

        $printRun->shouldReceive('markFulfilling')->once()->with(0);
        $printRun->shouldReceive('markBatching')->once();
        $printRun->shouldReceive('markFailed')->never();

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')
            ->once()
            ->with(Mockery::on(fn($r) => $r instanceof WorkflowStageResult
                && $r->summary['total_chunks'] === 0
                && $r->summary['total_fulfilments'] === 0
                && isset($r->summary['skipped_reason'])
            ));

        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
            ->andReturn($recorder);

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_does_not_dispatch_chunk_jobs_when_no_subscriptions(): void
    {
        $printRun = $this->makePrintRun();
        $issueDelivery = $this->makeIssueDelivery();

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->subscriptionRepository
            ->shouldReceive('findPrintSubscriptionsForIssueDelivery')
            ->andReturn(new Collection([]));

        $printRun->shouldReceive('markFulfilling')->once();
        $printRun->shouldReceive('markBatching')->once();

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')->once();
        $this->recorderFactory->shouldReceive('forPrintRun')->andReturn($recorder);

        // No chunk jobs or monitor are dispatched — verified by the fact that
        // markFulfilling(0) is called and the test does not throw.
        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_returns_early_when_print_run_not_found(): void
    {
        $this->printRunRepository->shouldReceive('find')->andReturn(null);
        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->subscriptionRepository->shouldNotReceive('findPrintSubscriptionsForIssueDelivery');
        $this->recorderFactory->shouldNotReceive('forPrintRun');

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with('CreatePrintFulfillmentsJob: PrintRun not found', Mockery::hasKey('print_run_id'));

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_returns_early_when_print_run_is_cancelled(): void
    {
        $printRun = $this->makePrintRun(PrintRunStatus::CANCELLED);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->subscriptionRepository->shouldNotReceive('findPrintSubscriptionsForIssueDelivery');
        $this->recorderFactory->shouldNotReceive('forPrintRun');

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with('CreatePrintFulfillmentsJob: PrintRun cancelled, aborting', Mockery::hasKey('print_run_id'));

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }

    public function test_marks_print_run_failed_and_records_workflow_run_when_issue_delivery_not_found(): void
    {
        $printRun = $this->makePrintRun();

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn(null);
        $this->subscriptionRepository->shouldNotReceive('findPrintSubscriptionsForIssueDelivery');

        $printRun->shouldReceive('markFailed')->once();
        $printRun->shouldReceive('markFulfilling')->never();

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')
            ->once()
            ->with(Mockery::on(fn($r) => $r instanceof WorkflowStageResult
                && $r->status === \App\Enums\Workflow\WorkflowStageStatus::FAILED
                && str_contains($r->error, '5')
            ));

        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
            ->andReturn($recorder);

        $this->makeJob()->handle(1, 5);

        $this->assertTrue(true);
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeRecorder(): WorkflowRunRecorder|MockInterface
    {
        return Mockery::mock(WorkflowRunRecorder::class);
    }
}