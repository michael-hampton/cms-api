<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\DTO\Subscriptions\WorkflowStageResult;
use App\Enums\Subscriptions\PrintBatchStatus;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\ExportPrintBatchJob;
use App\Models\IssueDelivery;
use App\Models\PrintBatch;
use App\Models\PrintRun;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Subscriptions\Printing\PrintBatchExportService;
use App\Services\Workflow\WorkflowRunRecorder;
use App\Services\Workflow\WorkflowRunRecorderFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class ExportPrintBatchJobTest extends FunctionalTestCase
{
    private PrintBatchRepository|MockInterface $batchRepository;
    private IssueDeliveryRepository|MockInterface $issueDeliveryRepository;
    private PrintBatchExportService|MockInterface $exportService;
    private WorkflowRunRecorderFactory|MockInterface $recorderFactory;
    private PrintRunRepository|MockInterface $printRunRepository;
    private Logger|MockInterface $logger;
    private ExportPrintBatchJob $job;

    public function test_calls_export_service_with_batch_and_issue_delivery(): void
    {
        $batch = $this->makeBatch(42);
        $issueDelivery = $this->makeIssueDelivery(7);
        $printRun = $this->makePrintRun(workflowRunId: 10);

        $this->batchRepository
            ->shouldReceive('find')
            ->once()
            ->with(42)
            ->andReturn($batch);

        $this->issueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with(7)
            ->andReturn($issueDelivery);

        $this->exportService
            ->shouldReceive('export')
            ->once()
            ->with($batch, $issueDelivery);

        $this->batchRepository
            ->shouldReceive('findByIssueDelivery')
            ->with(7)
            ->andReturn(collect([$batch]));

        $this->printRunRepository
            ->shouldReceive('findActiveForIssueDelivery')
            ->with(7)
            ->andReturn($printRun);

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')->once();

        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_3', WorkflowRunStatus::COMPLETE)
            ->andReturn($recorder);

        $this->job->handle(42, 7);

        $this->assertTrue(true);
    }

    public function test_exports_batch_and_records_completion_when_all_batches_exported(): void
    {
        $batch = $this->makeBatch(42);
        $issueDelivery = $this->makeIssueDelivery(7);
        $printRun = $this->makePrintRun(workflowRunId: 10);

        $this->batchRepository->shouldReceive('find')->with(42)->andReturn($batch);
        $this->issueDeliveryRepository->shouldReceive('find')->with(7)->andReturn($issueDelivery);
        $this->exportService->shouldReceive('export')->once()->with($batch, $issueDelivery);

        $this->batchRepository
            ->shouldReceive('findByIssueDelivery')
            ->with(7)
            ->andReturn(collect([$batch]));

        $this->printRunRepository
            ->shouldReceive('findActiveForIssueDelivery')
            ->with(7)
            ->andReturn($printRun);

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')
            ->once()
            ->with(Mockery::on(fn($r) => $r instanceof WorkflowStageResult
                && $r->summary['batch_count'] === 1
            ));

        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_3', WorkflowRunStatus::COMPLETE)
            ->andReturn($recorder);

        $this->job->handle(42, 7);

        $this->assertTrue(true);
    }

    public function test_does_not_record_completion_when_other_batches_still_pending(): void
    {
        $exportedBatch = $this->makeBatch(42);
        $pendingBatch = $this->makeBatch(43, PrintBatchStatus::PENDING->value);
        $issueDelivery = $this->makeIssueDelivery(7);

        $this->batchRepository->shouldReceive('find')->with(42)->andReturn($exportedBatch);
        $this->issueDeliveryRepository->shouldReceive('find')->with(7)->andReturn($issueDelivery);
        $this->exportService->shouldReceive('export')->once();

        $this->batchRepository
            ->shouldReceive('findByIssueDelivery')
            ->with(7)
            ->andReturn(collect([$exportedBatch, $pendingBatch]));

        $this->recorderFactory->shouldNotReceive('forPrintRun');
        $this->printRunRepository->shouldNotReceive('findActiveForIssueDelivery');

        $this->job->handle(42, 7);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Missing batch — bail without retry
    // -------------------------------------------------------------------------

    public function test_logs_and_returns_when_batch_not_found(): void
    {
        $this->batchRepository
            ->shouldReceive('find')
            ->once()
            ->with(99)
            ->andReturn(null);

        $this->exportService->shouldNotReceive('export');

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with('ExportPrintBatchJob: batch not found', Mockery::hasKey('batch_id'));

        $this->job->handle(99, 1);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Missing issue delivery — bail without retry
    // -------------------------------------------------------------------------

    public function test_logs_and_returns_when_issue_delivery_not_found(): void
    {
        $batch = $this->makeBatch(1);

        $this->batchRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($batch);

        $this->issueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->exportService->shouldNotReceive('export');

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with('ExportPrintBatchJob: issue delivery not found', Mockery::hasKey('issue_delivery_id'));

        $this->job->handle(1, 999);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Transport failure propagates — queue retries the job
    // -------------------------------------------------------------------------

    public function test_rethrows_exception_from_export_service_so_queue_retries(): void
    {
        $batch = $this->makeBatch(42);
        $issueDelivery = $this->makeIssueDelivery(7);
        $printRun = $this->makePrintRun(workflowRunId: 10);

        $this->batchRepository->shouldReceive('find')->andReturn($batch);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);

        $this->exportService
            ->shouldReceive('export')
            ->andThrow(new \RuntimeException('SFTP timed out'));

        $this->printRunRepository
            ->shouldReceive('findActiveForIssueDelivery')
            ->with(7)
            ->andReturn($printRun);

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')
            ->once()
            ->with(Mockery::on(fn($r) => $r instanceof WorkflowStageResult
                && $r->status === \App\Enums\Workflow\WorkflowStageStatus::FAILED
                && str_contains($r->error, 'SFTP timed out')
            ));

        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_3', WorkflowRunStatus::COMPLETE)
            ->andReturn($recorder);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SFTP timed out');

        $this->job->handle(42, 7);
    }

    public function test_records_failure_and_rethrows_when_export_service_throws(): void
    {
        $batch = $this->makeBatch(42);
        $issueDelivery = $this->makeIssueDelivery(7);
        $printRun = $this->makePrintRun(workflowRunId: 10);

        $this->batchRepository->shouldReceive('find')->with(42)->andReturn($batch);
        $this->issueDeliveryRepository->shouldReceive('find')->with(7)->andReturn($issueDelivery);

        $this->exportService
            ->shouldReceive('export')
            ->andThrow(new \RuntimeException('SFTP timed out'));

        $this->printRunRepository
            ->shouldReceive('findActiveForIssueDelivery')
            ->with(7)
            ->andReturn($printRun);

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')
            ->once()
            ->with(Mockery::on(fn($r) => $r instanceof WorkflowStageResult
                && $r->status === \App\Enums\Workflow\WorkflowStageStatus::FAILED
                && str_contains($r->error, 'SFTP timed out')
            ));

        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_3', WorkflowRunStatus::COMPLETE)
            ->andReturn($recorder);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SFTP timed out');

        $this->job->handle(42, 7);
    }

    public function test_rethrows_when_export_fails_and_no_active_print_run(): void
    {
        $batch = $this->makeBatch(42);
        $issueDelivery = $this->makeIssueDelivery(7);

        $this->batchRepository->shouldReceive('find')->andReturn($batch);
        $this->issueDeliveryRepository->shouldReceive('find')->andReturn($issueDelivery);

        $this->exportService
            ->shouldReceive('export')
            ->andThrow(new \RuntimeException('SFTP timed out'));

        $this->printRunRepository
            ->shouldReceive('findActiveForIssueDelivery')
            ->with(7)
            ->andReturn(null);

        $this->recorderFactory->shouldNotReceive('forPrintRun');

        $this->expectException(\RuntimeException::class);

        $this->job->handle(42, 7);
    }

    public function test_logs_warning_when_all_exported_but_no_active_print_run(): void
    {
        $batch = $this->makeBatch(42);
        $issueDelivery = $this->makeIssueDelivery(7);

        $this->batchRepository->shouldReceive('find')->with(42)->andReturn($batch);
        $this->issueDeliveryRepository->shouldReceive('find')->with(7)->andReturn($issueDelivery);
        $this->exportService->shouldReceive('export')->once();

        $this->batchRepository
            ->shouldReceive('findByIssueDelivery')
            ->with(7)
            ->andReturn(collect([$batch]));

        $this->printRunRepository
            ->shouldReceive('findActiveForIssueDelivery')
            ->with(7)
            ->andReturn(null);

        $this->recorderFactory->shouldNotReceive('forPrintRun');

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with(
                'ExportPrintBatchJob: all batches exported but no active PrintRun found',
                Mockery::hasKey('issue_delivery_id'),
            );

        $this->job->handle(42, 7);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->batchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->exportService = Mockery::mock(PrintBatchExportService::class);
        $this->recorderFactory = Mockery::mock(WorkflowRunRecorderFactory::class);
        $this->printRunRepository = Mockery::mock(PrintRunRepository::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->job = new ExportPrintBatchJob(
            batchRepository: $this->batchRepository,
            issueDeliveryRepository: $this->issueDeliveryRepository,
            exportService: $this->exportService,
            recorderFactory: $this->recorderFactory,
            printRunRepository: $this->printRunRepository,
            logger: $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Wire the IssueDelivery::find() static call via container binding so the
     * test does not touch the database.
     */
    private function expectIssueDeliveryFound(IssueDelivery $issueDelivery): void
    {
        // IssueDelivery::find is a static model call inside the job.
        // We bind the model in the container so the test controls what it returns.
        app()->bind(IssueDelivery::class, fn() => $issueDelivery);
    }

    private function makeRecorder(): WorkflowRunRecorder|MockInterface
    {
        return Mockery::mock(WorkflowRunRecorder::class);
    }

    private function makePrintRun(int $workflowRunId = 10): PrintRun|MockInterface
    {
        $printRun = Mockery::mock(PrintRun::class)->makePartial();
        $printRun->id = 1;
        $printRun->workflow_run_id = $workflowRunId;
        return $printRun;
    }

    private function makeBatch(int $id, $status = null): PrintBatch|MockInterface
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = $id;
        $batch->issue_delivery_id = 7;
        $batch->status = $status ?? PrintBatchStatus::BATCH_EXPORTED->value;
        return $batch;
    }

    private function makeIssueDelivery(int $id): IssueDelivery|MockInterface
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = $id;
        $delivery->issue_title = 'Spring Issue';
        return $delivery;
    }
}