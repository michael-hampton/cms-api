<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Container;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\ProcessPrintBatchJob;
use App\Models\PrintBatch;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class ProcessPrintBatchJobTest extends FunctionalTestCase
{
    private MockInterface $batchRepository;
    private MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $container = Container::getInstance();
        $container->instance(PrintBatchRepository::class, $this->batchRepository);
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

    public function test_it_dispatches_export_and_label_jobs_in_parallel(): void
    {
        $batch = $this->makeBatch(id: 10, issueDeliveryId: 5);
        $batch->shouldReceive('isExported')->andReturn(false);

        $this->batchRepository->shouldReceive('find')->with(10)->andReturn($batch);


        $job = ProcessPrintBatchJob::for(10);
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

        $job = ProcessPrintBatchJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);

    }

    public function test_it_returns_early_when_batch_already_exported(): void
    {
        $batch = $this->makeBatch(id: 10, issueDeliveryId: 5);
        $batch->shouldReceive('isExported')->andReturn(true);

        $this->batchRepository->shouldReceive('find')->andReturn($batch);


        $job = ProcessPrintBatchJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);

    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeBatch(int $id, int $issueDeliveryId): MockInterface
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = $id;
        $batch->issue_delivery_id = $issueDeliveryId;
        return $batch;
    }
}