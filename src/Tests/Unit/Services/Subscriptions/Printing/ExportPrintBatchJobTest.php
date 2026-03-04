<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\ExportPrintBatchJob;
use App\Models\IssueDelivery;
use App\Models\PrintBatch;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Services\Subscriptions\Printing\PrintBatchExportService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class ExportPrintBatchJobTest extends FunctionalTestCase
{
    private PrintBatchRepository|MockInterface $batchRepository;
    private IssueDeliveryRepository|MockInterface $issueDeliveryRepository;

    private PrintBatchExportService|MockInterface $exportService;
    private Logger|MockInterface $logger;
    private ExportPrintBatchJob $job;

    public function test_calls_export_service_with_batch_and_issue_delivery(): void
    {
        $batch = $this->makeBatch(42);
        $issueDelivery = $this->makeIssueDelivery(7);

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

        $this->job->handle(42, 7);

        $this->assertTrue(true);
    }

    private function makeBatch(int $id): PrintBatch|MockInterface
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = $id;
        return $batch;
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    private function makeIssueDelivery(int $id): IssueDelivery|MockInterface
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = $id;
        $delivery->issue_title = 'Spring Issue';
        return $delivery;
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

        $this->batchRepository
            ->shouldReceive('find')
            ->andReturn($batch);

        $this->issueDeliveryRepository
            ->shouldReceive('find')
            ->andReturn($issueDelivery);

        $this->exportService
            ->shouldReceive('export')
            ->andThrow(new \RuntimeException('SFTP timed out'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SFTP timed out/');

        $this->job->handle(42, 7);
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
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->job = new ExportPrintBatchJob(
            $this->batchRepository,
            $this->issueDeliveryRepository,
            $this->exportService,
            $this->logger,
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
}