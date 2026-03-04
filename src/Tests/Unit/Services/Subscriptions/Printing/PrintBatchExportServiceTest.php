<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\PrintBatch;
use App\Models\PrintFulfillment;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\Printing\Format\PrintExportFormatStrategy;
use App\Services\Subscriptions\Printing\PrintBatchExportService;
use App\Services\Subscriptions\Printing\Transport\PrintExportTransport;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class PrintBatchExportServiceTest extends FunctionalTestCase
{
    private PrintFulfillmentRepository|MockInterface $fulfillmentRepository;
    private PrintExportFormatStrategy|MockInterface $formatStrategy;
    private PrintExportTransport|MockInterface $transport;
    private Logger|MockInterface $logger;
    private PrintBatchExportService $service;

    public function test_exports_batch_successfully(): void
    {
        $batch = $this->makeBatch(42, attemptCount: 1);
        $issueDelivery = $this->makeIssueDelivery(7, 'Spring Issue');
        $fulfillments = [$this->makeFulfillment()];

        $this->fulfillmentRepository
            ->shouldReceive('findByBatch')
            ->once()
            ->with(42)
            ->andReturn($fulfillments);

        $this->formatStrategy
            ->shouldReceive('extension')
            ->andReturn('csv');

        $this->formatStrategy
            ->shouldReceive('generate')
            ->once()
            ->andReturn('batch_id,subscription_id,...');

        $this->transport
            ->shouldReceive('upload')
            ->once()
            ->with(Mockery::pattern('/^batch_42_v1_\d{8}_\d{6}\.csv$/'), 'batch_id,subscription_id,...');

        $this->fulfillmentRepository
            ->shouldReceive('markAllExported')
            ->once()
            ->with(42);

        $batch->shouldReceive('markExporting')->once();
        $batch->shouldReceive('markExported')->once();

        $this->service->export($batch, $issueDelivery);

        $this->assertTrue(true);
    }

    private function makeBatch(int $id, bool $exported = false, bool $exporting = false, int $attemptCount = 1): PrintBatch|MockInterface
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = $id;
        $batch->export_attempt_count = $attemptCount;
        $batch->shouldReceive('isExported')->andReturn($exported);
        $batch->shouldReceive('isExporting')->andReturn($exporting);
        $batch->shouldReceive('markExporting')->byDefault();
        $batch->shouldReceive('markExported')->byDefault();
        $batch->shouldReceive('markFailed')->byDefault();
        return $batch;
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    private function makeIssueDelivery(int $id, ?string $title): IssueDelivery|MockInterface
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = $id;
        $delivery->issue_title = $title;
        return $delivery;
    }

    // -------------------------------------------------------------------------
    // Filename versioning
    // -------------------------------------------------------------------------

    private function makeFulfillment(): PrintFulfillment|MockInterface
    {
        $f = Mockery::mock(PrintFulfillment::class)->makePartial();
        $f->id = 1;
        return $f;
    }

    public function test_filename_includes_version_counter(): void
    {
        $batch = $this->makeBatch(5, attemptCount: 1);
        $issueDelivery = $this->makeIssueDelivery(1, 'Test');

        $this->fulfillmentRepository->shouldReceive('findByBatch')->andReturn([]);
        $this->formatStrategy->shouldReceive('extension')->andReturn('csv');
        $this->formatStrategy->shouldReceive('generate')->andReturn('csv-content');
        $this->fulfillmentRepository->shouldReceive('markAllExported');

        $capturedFilename = null;

        $this->transport
            ->shouldReceive('upload')
            ->once()
            ->withArgs(function (string $path) use (&$capturedFilename) {
                $capturedFilename = $path;
                return true;
            });

        $batch->shouldReceive('markExporting');
        $batch->shouldReceive('markExported');

        $this->service->export($batch, $issueDelivery);

        $this->assertMatchesRegularExpression('/^batch_5_v1_\d{8}_\d{6}\.csv$/', $capturedFilename);
    }

    // -------------------------------------------------------------------------
    // Idempotency — skip already exported batches
    // -------------------------------------------------------------------------

    public function test_re_export_uses_incremented_version(): void
    {
        // Simulate a second export attempt — attempt_count is already 2
        $batch = $this->makeBatch(5, attemptCount: 2);
        $issueDelivery = $this->makeIssueDelivery(1, 'Test');

        $this->fulfillmentRepository->shouldReceive('findByBatch')->andReturn([]);
        $this->formatStrategy->shouldReceive('extension')->andReturn('csv');
        $this->formatStrategy->shouldReceive('generate')->andReturn('csv-content');
        $this->fulfillmentRepository->shouldReceive('markAllExported');

        $capturedFilename = null;

        $this->transport
            ->shouldReceive('upload')
            ->once()
            ->withArgs(function (string $path) use (&$capturedFilename) {
                $capturedFilename = $path;
                return true;
            });

        $batch->shouldReceive('markExporting');
        $batch->shouldReceive('markExported');

        $this->service->export($batch, $issueDelivery);

        $this->assertMatchesRegularExpression('/^batch_5_v2_\d{8}_\d{6}\.csv$/', $capturedFilename);
    }

    public function test_skips_already_exported_batch(): void
    {
        $batch = $this->makeBatch(1, exported: true);
        $issueDelivery = $this->makeIssueDelivery(1, 'Issue');

        $this->fulfillmentRepository->shouldNotReceive('findByBatch');
        $this->transport->shouldNotReceive('upload');

        $this->service->export($batch, $issueDelivery);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Batch size guard
    // -------------------------------------------------------------------------

    public function test_skips_batch_currently_exporting(): void
    {
        $batch = $this->makeBatch(1, exporting: true);
        $issueDelivery = $this->makeIssueDelivery(1, 'Issue');

        $this->fulfillmentRepository->shouldNotReceive('findByBatch');
        $this->transport->shouldNotReceive('upload');

        $this->service->export($batch, $issueDelivery);

        $this->assertTrue(true);
    }

    public function test_throws_when_fulfillment_count_exceeds_max_size(): void
    {
        $service = $this->makeService(maxBatchSize: 3);
        $batch = $this->makeBatch(42, attemptCount: 1);
        $issueDelivery = $this->makeIssueDelivery(7, 'Issue');

        // 4 fulfillments against a limit of 3
        $fulfillments = array_fill(0, 4, $this->makeFulfillment());

        $this->fulfillmentRepository
            ->shouldReceive('findByBatch')
            ->andReturn($fulfillments);

        $this->transport->shouldNotReceive('upload');

        $batch->shouldReceive('markExporting')->once();
        $batch->shouldReceive('markFailed')->once();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/exceeds maximum export size/');

        $service->export($batch, $issueDelivery);
    }

    // -------------------------------------------------------------------------
    // Transport failure
    // -------------------------------------------------------------------------

    private function makeService(int $maxBatchSize = 5000): PrintBatchExportService
    {
        return new PrintBatchExportService(
            $this->fulfillmentRepository,
            $this->formatStrategy,
            $this->transport,
            $this->logger,
            $maxBatchSize,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_does_not_throw_when_fulfillments_exactly_at_limit(): void
    {
        $service = $this->makeService(maxBatchSize: 2);
        $batch = $this->makeBatch(42, attemptCount: 1);
        $issueDelivery = $this->makeIssueDelivery(7, 'Issue');

        $fulfillments = array_fill(0, 2, $this->makeFulfillment());

        $this->fulfillmentRepository->shouldReceive('findByBatch')->andReturn($fulfillments);
        $this->formatStrategy->shouldReceive('extension')->andReturn('csv');
        $this->formatStrategy->shouldReceive('generate')->andReturn('csv');
        $this->transport->shouldReceive('upload')->once();
        $this->fulfillmentRepository->shouldReceive('markAllExported');

        $batch->shouldReceive('markExporting');
        $batch->shouldReceive('markExported');

        $service->export($batch, $issueDelivery);

        $this->assertTrue(true);
    }

    public function test_marks_batch_failed_and_rethrows_on_transport_error(): void
    {
        $batch = $this->makeBatch(42, attemptCount: 1);
        $issueDelivery = $this->makeIssueDelivery(7, 'Issue');

        $this->fulfillmentRepository->shouldReceive('findByBatch')->andReturn([]);
        $this->formatStrategy->shouldReceive('extension')->andReturn('csv');
        $this->formatStrategy->shouldReceive('generate')->andReturn('csv');

        $this->transport
            ->shouldReceive('upload')
            ->andThrow(new \RuntimeException('Connection refused'));

        $batch->shouldReceive('markExporting')->once();
        $batch->shouldReceive('markFailed')->once();
        $batch->shouldNotReceive('markExported');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Connection refused/');

        $this->service->export($batch, $issueDelivery);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fulfillmentRepository = Mockery::mock(PrintFulfillmentRepository::class);
        $this->formatStrategy = Mockery::mock(PrintExportFormatStrategy::class);
        $this->transport = Mockery::mock(PrintExportTransport::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new PrintBatchExportService(
            $this->fulfillmentRepository,
            $this->formatStrategy,
            $this->transport,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}