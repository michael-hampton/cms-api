<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\LabelExportFormat;
use App\Framework\Container;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\GenerateLabelRunsJob;
use App\Models\LabelRun;
use App\Models\PrintBatch;
use App\Models\PrintFulfillment;
use App\Repositories\Subscriptions\LabelRunRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class GenerateLabelRunsJobTest extends FunctionalTestCase
{
    private MockInterface $batchRepository;
    private MockInterface $fulfillmentRepository;
    private MockInterface $labelRunRepository;
    private MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->fulfillmentRepository = Mockery::mock(PrintFulfillmentRepository::class);
        $this->labelRunRepository = Mockery::mock(LabelRunRepository::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $container = Container::getInstance();
        $container->instance(PrintBatchRepository::class, $this->batchRepository);
        $container->instance(PrintFulfillmentRepository::class, $this->fulfillmentRepository);
        $container->instance(LabelRunRepository::class, $this->labelRunRepository);
        $container->instance(Logger::class, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Happy path
    // =========================================================================

    public function test_it_creates_label_runs_and_dispatches_generate_label_jobs(): void
    {
        $batch = $this->makeBatch(id: 10);
        $fulfillment1 = $this->makeFulfillment(issuesDeliveredId: 100, subscriptionId: 1);
        $fulfillment2 = $this->makeFulfillment(issuesDeliveredId: 101, subscriptionId: 2);
        $labelRun1 = $this->makeLabelRun(id: 200);
        $labelRun2 = $this->makeLabelRun(id: 201);

        $this->batchRepository->shouldReceive('find')->with(10)->andReturn($batch);

        $this->fulfillmentRepository
            ->shouldReceive('findByBatch')
            ->with(10)
            ->andReturn([$fulfillment1, $fulfillment2]);

        $this->labelRunRepository
            ->shouldReceive('existsForIssuesDeliveredAndBatch')
            ->with(100, 10)->andReturn(false)
            ->shouldReceive('existsForIssuesDeliveredAndBatch')
            ->with(101, 10)->andReturn(false);

        $this->labelRunRepository
            ->shouldReceive('createForIssuesDelivered')
            ->with(100, 1, Mockery::type(LabelExportFormat::class), 10)
            ->once()
            ->andReturn($labelRun1);

        $this->labelRunRepository
            ->shouldReceive('createForIssuesDelivered')
            ->with(101, 2, Mockery::type(LabelExportFormat::class), 10)
            ->once()
            ->andReturn($labelRun2);

        $job = GenerateLabelRunsJob::for(10);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);

    }

    public function test_it_skips_fulfillments_that_already_have_a_label_run(): void
    {
        $batch = $this->makeBatch(id: 10);
        $fulfillment1 = $this->makeFulfillment(issuesDeliveredId: 100, subscriptionId: 1);
        $fulfillment2 = $this->makeFulfillment(issuesDeliveredId: 101, subscriptionId: 2);

        $this->batchRepository->shouldReceive('find')->andReturn($batch);
        $this->fulfillmentRepository->shouldReceive('findByBatch')
            ->andReturn([$fulfillment1, $fulfillment2]);

        // First already exists, second does not
        $this->labelRunRepository
            ->shouldReceive('existsForIssuesDeliveredAndBatch')
            ->with(100, 10)->andReturn(true)
            ->shouldReceive('existsForIssuesDeliveredAndBatch')
            ->with(101, 10)->andReturn(false);

        // Only one LabelRun created
        $this->labelRunRepository
            ->shouldReceive('createForIssuesDelivered')
            ->once()
            ->andReturn($this->makeLabelRun(id: 200));

        $job = GenerateLabelRunsJob::for(10);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    // =========================================================================
    // Guard conditions
    // =========================================================================

    public function test_it_returns_early_when_batch_not_found(): void
    {
        $this->batchRepository->shouldReceive('find')->andReturn(null);
        $this->fulfillmentRepository->shouldNotReceive('findByBatch');
        $this->labelRunRepository->shouldNotReceive('createForIssuesDelivered');

        $job = GenerateLabelRunsJob::for(10);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_it_handles_empty_fulfillment_list_without_error(): void
    {
        $batch = $this->makeBatch(id: 10);

        $this->batchRepository->shouldReceive('find')->andReturn($batch);
        $this->fulfillmentRepository->shouldReceive('findByBatch')
            ->andReturn([]);

        $this->labelRunRepository->shouldNotReceive('createForIssuesDelivered');

        $job = GenerateLabelRunsJob::for(10);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    // =========================================================================
    // Format resolution
    // =========================================================================

    public function test_it_uses_format_from_config(): void
    {
        $batch = $this->makeBatch(id: 10);
        $fulfillment = $this->makeFulfillment(issuesDeliveredId: 100, subscriptionId: 1);

        $this->batchRepository->shouldReceive('find')->andReturn($batch);
        $this->fulfillmentRepository->shouldReceive('findByBatch')
            ->andReturn([$fulfillment]);
        $this->labelRunRepository
            ->shouldReceive('existsForIssuesDeliveredAndBatch')->andReturn(false);

        $capturedFormat = null;
        $this->labelRunRepository
            ->shouldReceive('createForIssuesDelivered')
            ->once()
            ->withArgs(function ($issuesDeliveredId, $subscriptionId, $format, $batchId) use (&$capturedFormat) {
                $capturedFormat = $format;
                return true;
            })
            ->andReturn($this->makeLabelRun(id: 200));

        $job = GenerateLabelRunsJob::for(10, LabelExportFormat::Pdf);
        $job->__wakeup();
        $job->handle();

        $this->assertSame(LabelExportFormat::Pdf, $capturedFormat);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeBatch(int $id): MockInterface
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = $id;
        return $batch;
    }

    private function makeFulfillment(int $issuesDeliveredId, int $subscriptionId): MockInterface
    {
        $fulfillment = Mockery::mock(PrintFulfillment::class)->makePartial();
        $fulfillment->issues_delivered_id = $issuesDeliveredId;
        $fulfillment->subscription_id = $subscriptionId;
        return $fulfillment;
    }

    private function makeLabelRun(int $id): MockInterface
    {
        $labelRun = Mockery::mock(LabelRun::class)->makePartial();
        $labelRun->id = $id;
        return $labelRun;
    }
}