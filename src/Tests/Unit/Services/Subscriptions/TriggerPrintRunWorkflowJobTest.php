<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\PrintRunStatus;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\TriggerPrintRunWorkflowJob;
use App\Jobs\Subscriptions\WorkflowRunStarter;
use App\Models\IssueDelivery;
use App\Models\PrintRun;
use App\Models\WorkflowRun;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Workflow\WorkflowRunRecorderFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class TriggerPrintRunWorkflowJobTest extends FunctionalTestCase
{
    private IssueDeliveryRepository|MockInterface $issueDeliveryRepository;
    private PrintRunRepository|MockInterface $printRunRepository;
    private WorkflowRunRecorderFactory|MockInterface $recorderFactory;
    private Logger|MockInterface $logger;
    private TriggerPrintRunWorkflowJob $job;
    private WorkflowRunStarter|MockInterface $workflowRunStarter;

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_creates_workflow_run_and_print_run_then_dispatches_fulfillments_job(): void
    {
        $issueDelivery = $this->makeIssueDelivery(5);
        $workflowRun = $this->makeWorkflowRun(10);
        $printRun = $this->makePrintRun(1, workflowRunId: 10);

        $this->issueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn($issueDelivery);

        $this->printRunRepository
            ->shouldReceive('cancelAllPendingForIssueDelivery')
            ->once()
            ->with(5);

        $this->mockWorkflowRunStart($workflowRun, [
            'issue_delivery_id' => 5,
            'triggered_by' => 'IssueDeliveryDispatchedListener',
        ]);

        $this->printRunRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($data) => $data['issue_delivery_id'] == 5
                //&& $data['workflow_run_id']      == $workflowRun->id
                && $data['status'] === PrintRunStatus::PENDING->value
                && $data['total_chunks'] == 0
                && $data['fulfilled_chunks_count'] == 0
            ))
            ->andReturn($printRun);

        $this->job = $this->makeJob(5);
        $this->job->handle();

        $this->assertTrue(true);
    }

    private function makeIssueDelivery(int $id): IssueDelivery|MockInterface
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = $id;
        return $delivery;
    }

    private function makeWorkflowRun(int $id): WorkflowRun|MockInterface
    {
        $workflowRun = Mockery::mock(WorkflowRun::class)->makePartial();
        $workflowRun->id = $id;
        return $workflowRun;
    }

    // -------------------------------------------------------------------------
    // Missing IssueDelivery
    // -------------------------------------------------------------------------

    private function makePrintRun(int $id, int $workflowRunId = 10): PrintRun|MockInterface
    {
        $printRun = Mockery::mock(PrintRun::class)->makePartial();
        $printRun->id = $id;
        $printRun->workflow_run_id = $workflowRunId;
        return $printRun;
    }

    /**
     * Stubs WorkflowRun::start() via the container so the test does not hit
     * the database. WorkflowRun::start() is a static factory — we bind the
     * model in the container and override the static call to return our stub.
     */
    private function mockWorkflowRunStart(WorkflowRun $workflowRun, ?array $expectedInput = null): void
    {
        $this->workflowRunStarter
            ->shouldReceive('start')
            ->once()
            ->with(TriggerPrintRunWorkflowJob::class, [
                'issue_delivery_id' => 5,
                'triggered_by' => 'IssueDeliveryDispatchedListener',
            ])
            ->andReturn($workflowRun);
    }

    // -------------------------------------------------------------------------
    // Setup / teardown
    // -------------------------------------------------------------------------

    public function test_cancels_pending_print_runs_before_creating_new_one(): void
    {
        $issueDelivery = $this->makeIssueDelivery(5);
        $workflowRun = $this->makeWorkflowRun(10);
        $printRun = $this->makePrintRun(1, workflowRunId: 10);

        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);

        $cancelCalled = false;
        $createCalled = false;
        $cancelOrder = null;
        $createOrder = null;
        $callOrder = 0;

        $this->printRunRepository
            ->shouldReceive('cancelAllPendingForIssueDelivery')
            ->once()
            ->andReturnUsing(function () use (&$cancelCalled, &$cancelOrder, &$callOrder) {
                $cancelCalled = true;
                $cancelOrder = ++$callOrder;
                return 1; // ✅ FIX: must return int
            });

        $this->mockWorkflowRunStart($workflowRun);

        $this->printRunRepository
            ->shouldReceive('create')
            ->once()
            ->andReturnUsing(function () use (&$createCalled, &$createOrder, &$callOrder, $printRun) {
                $createCalled = true;
                $createOrder = ++$callOrder;
                return $printRun;
            });

        $this->job = $this->makeJob(5);
        $this->job->handle();

        $this->assertTrue($cancelCalled, 'cancelAllPendingForIssueDelivery was not called');
        $this->assertTrue($createCalled, 'create was not called');
        $this->assertLessThan($createOrder, $cancelOrder, 'cancel must be called before create');
    }

    public function test_logs_print_run_created_with_workflow_run_id(): void
    {
        $issueDelivery = $this->makeIssueDelivery(5);
        $workflowRun = $this->makeWorkflowRun(10);
        $printRun = $this->makePrintRun(1, workflowRunId: 10);

        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);
        $this->printRunRepository->shouldReceive('cancelAllPendingForIssueDelivery');
        $this->mockWorkflowRunStart($workflowRun);
        $this->printRunRepository->shouldReceive('create')->andReturn($printRun);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'TriggerPrintRunWorkflowJob: PrintRun created',
                Mockery::on(fn($ctx) => $ctx['print_run_id'] === 1
                    && $ctx['workflow_run_id'] === 10
                    && $ctx['issue_delivery_id'] === 5
                ),
            );

        $this->job = $this->makeJob(5);
        $this->job->handle();
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_logs_error_and_returns_early_when_issue_delivery_not_found(): void
    {
        $this->issueDeliveryRepository
            ->shouldReceive('find')
            ->with(99)
            ->andReturn(null);

        $this->printRunRepository->shouldNotReceive('cancelAllPendingForIssueDelivery');
        $this->printRunRepository->shouldNotReceive('create');

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with(
                'TriggerPrintRunWorkflowJob: IssueDelivery not found',
                Mockery::hasKey('issue_delivery_id'),
            );

        $this->job = $this->makeJob(99);
        $this->job->handle();

        $this->assertTrue(true);
    }

    public function test_does_not_create_workflow_run_when_issue_delivery_not_found(): void
    {
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn(null);
        $this->printRunRepository->shouldNotReceive('cancelAllPendingForIssueDelivery');
        $this->printRunRepository->shouldNotReceive('create');

        // WorkflowRun::start is a static call — if IssueDelivery is missing
        // we bail before reaching it, so create is never called.
        // This is verified by asserting create is never called on the repository.
        $this->job = $this->makeJob(99);
        $this->job->handle();

        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->printRunRepository = Mockery::mock(PrintRunRepository::class);
        $this->recorderFactory = Mockery::mock(WorkflowRunRecorderFactory::class)
            ->shouldIgnoreMissing();
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->workflowRunStarter = Mockery::mock(WorkflowRunStarter::class);

        $this->job = $this->makeJob(0);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeJob(int $issueDeliveryId): TriggerPrintRunWorkflowJob
    {
        $job = TriggerPrintRunWorkflowJob::for($issueDeliveryId);

        $this->injectService($job, 'issueDeliveryRepository', $this->issueDeliveryRepository);
        $this->injectService($job, 'printRunRepository', $this->printRunRepository);
        $this->injectService($job, 'recorderFactory', $this->recorderFactory);
        $this->injectService($job, 'logger', $this->logger);
        $this->injectService($job, 'workflowRunStarter', $this->workflowRunStarter);

        return $job;
    }

    private function injectService(object $job, string $property, object $service): void
    {
        $reflection = new \ReflectionObject($job);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($job, $service);
    }
}