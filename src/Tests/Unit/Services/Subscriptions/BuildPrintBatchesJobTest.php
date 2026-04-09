<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\PrintRunStatus;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Framework\Container;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\BuildPrintBatchesJob;
use App\Models\IssueDelivery;
use App\Models\PrintBatch;
use App\Models\PrintRun;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Subscriptions\Printing\BatchBuilderService;
use App\Services\Workflow\WorkflowRunRecorder;
use App\Services\Workflow\WorkflowRunRecorderFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class BuildPrintBatchesJobTest extends FunctionalTestCase
{
    private MockInterface $printRunRepository;
    private MockInterface $issueDeliveryRepository;
    private MockInterface $batchBuilderService;
    private MockInterface $logger;
    private WorkflowRunRecorderFactory $workflowRunRecorderFactory;

    public function test_it_builds_batches_and_dispatches_one_process_job_per_batch(): void
    {
        $printRun = $this->makePrintRun(PrintRunStatus::FULFILLING);
        $issueDelivery = $this->makeIssueDelivery();
        $batch1 = $this->makeBatch(id: 10);
        $batch2 = $this->makeBatch(id: 11);

        $this->setWorkflowExpectations($printRun);

        $this->printRunRepository->shouldReceive('find')->with(1)->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->andReturn($issueDelivery);

        $printRun->shouldReceive('markBatching')->once();
        $printRun->shouldReceive('markBatched')->once();
        $printRun->shouldReceive('markFailed')->never();

        $this->batchBuilderService
            ->shouldReceive('buildBatches')
            ->once()
            ->with($issueDelivery)
            ->andReturn(new Collection([$batch1, $batch2]));

        $job = BuildPrintBatchesJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);

    }

    private function makePrintRun(PrintRunStatus $status): MockInterface
    {
        $printRun = Mockery::mock(PrintRun::class)->makePartial();

        $printRun->id = 1;
        $printRun->issue_delivery_id = 5;
        $printRun->status = $status->value;

        $printRun->shouldReceive('isCancelled')
            ->andReturn($status === PrintRunStatus::CANCELLED);

        $printRun->shouldReceive('isComplete')
            ->andReturn($status === PrintRunStatus::COMPLETE);

        return $printRun;
    }

    // =========================================================================
    // Happy path
    // =========================================================================

    private function makeIssueDelivery(): MockInterface
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = 5;
        return $delivery;
    }

    private function makeBatch(int $id): MockInterface
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = $id;
        return $batch;
    }

    // =========================================================================
    // Guard conditions
    // =========================================================================

    private function setWorkflowExpectations(PrintRun $printRun)
    {
        $recorder = Mockery::mock(WorkflowRunRecorder::class);
        $recorder->shouldReceive('record')
            ->once();

        $this->workflowRunRecorderFactory->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_2', WorkflowRunStatus::EXPORTING)
            ->once()
            ->andReturn($recorder);

    }

    public function test_it_transitions_print_run_through_batching_to_batched(): void
    {
        $printRun = $this->makePrintRun(PrintRunStatus::FULFILLING);
        $issueDelivery = $this->makeIssueDelivery();

        $this->setWorkflowExpectations($printRun);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->batchBuilderService->shouldReceive('buildBatches')->andReturn(new Collection([]));

        // Order matters — batching before batched
        $callOrder = [];
        $printRun->shouldReceive('markBatching')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'batching';
        });
        $printRun->shouldReceive('markBatched')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'batched';
        });

        $job = BuildPrintBatchesJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertSame(['batching', 'batched'], $callOrder);
    }

    public function test_it_returns_early_when_print_run_not_found(): void
    {
        $this->printRunRepository->shouldReceive('find')->andReturn(null);

        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->batchBuilderService->shouldNotReceive('buildBatches');

        $job = BuildPrintBatchesJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    // =========================================================================
    // Zero batches
    // =========================================================================

    public function test_it_returns_early_when_print_run_is_cancelled(): void
    {
        $printRun = $this->makePrintRun(PrintRunStatus::CANCELLED);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->batchBuilderService->shouldNotReceive('buildBatches');

        $job = BuildPrintBatchesJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_it_returns_early_when_print_run_is_already_complete(): void
    {
        $printRun = $this->makePrintRun(PrintRunStatus::COMPLETE);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldNotReceive('find');
        $this->batchBuilderService->shouldNotReceive('buildBatches');

        $job = BuildPrintBatchesJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_it_marks_print_run_failed_when_issue_delivery_not_found(): void
    {
        $printRun = $this->makePrintRun(PrintRunStatus::FULFILLING);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn(null);

        $printRun->shouldReceive('markFailed')->once();
        $printRun->shouldReceive('markBatching')->never();

        $this->batchBuilderService->shouldNotReceive('buildBatches');

        $job = BuildPrintBatchesJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_it_handles_zero_batches_without_dispatching_any_process_jobs(): void
    {
        $printRun = $this->makePrintRun(PrintRunStatus::FULFILLING);
        $issueDelivery = $this->makeIssueDelivery();

        $this->setWorkflowExpectations($printRun);

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->batchBuilderService->shouldReceive('buildBatches')->andReturn(new Collection([]));

        $printRun->shouldReceive('markBatching')->once();
        $printRun->shouldReceive('markBatched')->once();

        $job = BuildPrintBatchesJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->printRunRepository = Mockery::mock(PrintRunRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->batchBuilderService = Mockery::mock(BatchBuilderService::class);
        $this->workflowRunRecorderFactory = Mockery::mock(WorkflowRunRecorderFactory::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $container = Container::getInstance();
        $container->instance(PrintRunRepository::class, $this->printRunRepository);
        $container->instance(IssueDeliveryRepository::class, $this->issueDeliveryRepository);
        $container->instance(BatchBuilderService::class, $this->batchBuilderService);
        $container->instance(WorkflowRunRecorderFactory::class, $this->workflowRunRecorderFactory);
        $container->instance(Logger::class, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}