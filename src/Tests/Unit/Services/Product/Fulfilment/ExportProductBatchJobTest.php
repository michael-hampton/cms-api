<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Product\Fulfilment;

use App\Framework\Container;
use App\Framework\Support\Logger;
use App\Jobs\Products\ExportProductBatchJob;
use App\Models\ProductBatch;
use App\Repositories\Product\ProductBatchRepository;
use App\Services\Product\Fulfilment\ProductBatchExportService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ExportProductBatchJobTest extends TestCase
{
    private ProductBatchRepository&MockInterface $batchRepository;
    private ProductBatchExportService&MockInterface $exportService;
    private Logger&MockInterface $logger;

    public function test_it_delegates_to_export_service(): void
    {
        $batch = Mockery::mock(ProductBatch::class);

        $this->batchRepository->shouldReceive('find')->with(5)->andReturn($batch);
        $this->exportService->shouldReceive('export')->once()->with($batch);

        $job = ExportProductBatchJob::for(5);
        $job->__wakeup();
        $job->handle();

        $this->addToAssertionCount(1);
    }

    public function test_it_returns_early_when_batch_not_found(): void
    {
        $this->batchRepository->shouldReceive('find')->with(99)->andReturn(null);
        $this->exportService->shouldNotReceive('export');

        $this->logger->shouldReceive('error')->once()->with(
            Mockery::pattern('/batch not found/'),
            Mockery::any(),
        );

        $job = ExportProductBatchJob::for(99);
        $job->__wakeup();
        $job->handle();

        $this->addToAssertionCount(1);
    }

    public function test_it_propagates_exceptions_from_export_service(): void
    {
        $batch = Mockery::mock(ProductBatch::class);
        $this->batchRepository->shouldReceive('find')->andReturn($batch);

        $this->exportService
            ->shouldReceive('export')
            ->andThrow(new \RuntimeException('transport failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('transport failed');

        $job = ExportProductBatchJob::for(5);
        $job->__wakeup();
        $job->handle();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->batchRepository = Mockery::mock(ProductBatchRepository::class);
        $this->exportService = Mockery::mock(ProductBatchExportService::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->logger->shouldReceive('error')->byDefault();

        $container = Container::getInstance();
        $container->instance(ProductBatchRepository::class, $this->batchRepository);
        $container->instance(ProductBatchExportService::class, $this->exportService);
        $container->instance(Logger::class, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

