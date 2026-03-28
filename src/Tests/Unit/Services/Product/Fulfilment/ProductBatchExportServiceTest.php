<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Product\Fulfilment;

use App\Framework\Support\Logger;
use App\Models\ProductBatch;
use App\Models\ProductFulfilment;
use App\Repositories\Product\ProductFulfilmentRepository;
use App\Services\Product\Fulfilment\ProductBatchExportService;
use App\Services\Subscriptions\Printing\Format\PrintExportFormatStrategy;
use App\Services\Subscriptions\Printing\Transport\PrintExportTransport;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ProductBatchExportServiceTest extends TestCase
{
    private ProductFulfilmentRepository&MockInterface $fulfilmentRepository;
    private PrintExportFormatStrategy&MockInterface $formatStrategy;
    private PrintExportTransport&MockInterface $transport;
    private Logger&MockInterface $logger;

    public function test_it_exports_a_batch_successfully(): void
    {
        $batch = $this->makeBatch(id: 1, attemptCount: 1);
        $fulfilments = [$this->makeFulfilment(), $this->makeFulfilment()];

        $batch->shouldReceive('markExporting')->once();
        $batch->shouldReceive('markExported')->once()->with(Mockery::pattern('/product_batch_1_v1_/'));

        $this->fulfilmentRepository->shouldReceive('findByBatch')->with(1)->andReturn($fulfilments);
        $this->fulfilmentRepository->shouldReceive('markAllExported')->once()->with(1);

        $this->formatStrategy->shouldReceive('generate')->once()->andReturn('csv-contents');
        $this->formatStrategy->shouldReceive('extension')->andReturn('csv');
        $this->transport->shouldReceive('upload')->once();

        $this->makeService()->export($batch);
        $this->assertTrue(true);
    }

    private function makeBatch(int $id, int $attemptCount): ProductBatch&MockInterface
    {
        $batch = Mockery::mock(ProductBatch::class)->makePartial();
        $batch->shouldReceive('isExported')->andReturn(false)->byDefault();
        $batch->shouldReceive('isExporting')->andReturn(false)->byDefault();
        $batch->id = $id;
        $batch->export_attempt_count = $attemptCount;

        return $batch;
    }

    private function makeFulfilment(): ProductFulfilment&MockInterface
    {
        return Mockery::mock(ProductFulfilment::class);
    }

    private function makeService(int $maxBatchSize = 5000): ProductBatchExportService
    {
        return new ProductBatchExportService(
            $this->fulfilmentRepository,
            $this->formatStrategy,
            $this->transport,
            $this->logger,
            $maxBatchSize,
        );
    }

    public function test_it_skips_export_when_batch_is_already_exported(): void
    {
        $batch = Mockery::mock(ProductBatch::class)->makePartial();
        $batch->id = 1;
        $batch->shouldReceive('isExported')->andReturn(true);
        $batch->shouldReceive('isExporting')->andReturn(false);

        // Nothing beyond the guard checks should be called.
        $this->fulfilmentRepository->shouldNotReceive('findByBatch');
        $this->transport->shouldNotReceive('upload');

        $this->makeService()->export($batch);
        $this->assertTrue(true);
    }

    public function test_it_skips_export_when_batch_is_currently_exporting(): void
    {
        $batch = Mockery::mock(ProductBatch::class)->makePartial();
        $batch->id = 1;
        $batch->shouldReceive('isExported')->andReturn(false);
        $batch->shouldReceive('isExporting')->andReturn(true);

        $this->fulfilmentRepository->shouldNotReceive('findByBatch');

        $this->makeService()->export($batch);
        $this->assertTrue(true);
    }

    public function test_it_marks_batch_failed_and_rethrows_on_transport_error(): void
    {
        $batch = $this->makeBatch(id: 2, attemptCount: 1);
        $batch->shouldReceive('markExporting')->once();
        $batch->shouldReceive('markFailed')->once();

        $this->fulfilmentRepository->shouldReceive('findByBatch')->andReturn([$this->makeFulfilment()]);
        $this->formatStrategy->shouldReceive('generate')->andReturn('csv');
        $this->formatStrategy->shouldReceive('extension')->andReturn('csv');

        $this->transport
            ->shouldReceive('upload')
            ->andThrow(new \RuntimeException('SFTP connection refused'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SFTP connection refused');

        $this->makeService()->export($batch);
    }

    public function test_it_throws_when_batch_exceeds_max_size(): void
    {
        $batch = $this->makeBatch(id: 3, attemptCount: 1);
        $batch->shouldReceive('markExporting')->once();
        $batch->shouldReceive('markFailed')->once();

        // Return 3 fulfilments against a max of 2.
        $this->fulfilmentRepository
            ->shouldReceive('findByBatch')
            ->andReturn([$this->makeFulfilment(), $this->makeFulfilment(), $this->makeFulfilment()]);

        $this->transport->shouldNotReceive('upload');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds maximum export size');

        $this->makeService(maxBatchSize: 2)->export($batch);
    }

    public function test_it_marks_all_fulfilments_exported_before_marking_batch_done(): void
    {
        $batch = $this->makeBatch(id: 4, attemptCount: 1);
        $batch->shouldReceive('markExporting')->once();

        $this->fulfilmentRepository->shouldReceive('findByBatch')->andReturn([$this->makeFulfilment()]);
        $this->formatStrategy->shouldReceive('generate')->andReturn('csv');
        $this->formatStrategy->shouldReceive('extension')->andReturn('csv');
        $this->transport->shouldReceive('upload')->once();

        // markAllExported must be called before markExported (ordering matters).
        $callOrder = [];
        $this->fulfilmentRepository
            ->shouldReceive('markAllExported')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'markAllExported';
            });

        $batch->shouldReceive('markExported')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'markExported';
            });

        $this->makeService()->export($batch);

        $this->assertSame(['markAllExported', 'markExported'], $callOrder);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function test_it_generates_a_versioned_filename_using_attempt_count(): void
    {
        $batch = $this->makeBatch(id: 7, attemptCount: 3);
        $batch->shouldReceive('markExporting')->once();
        $batch->shouldReceive('markExported')->once()->with(Mockery::pattern('/product_batch_7_v3_/'));

        $this->fulfilmentRepository->shouldReceive('findByBatch')->andReturn([$this->makeFulfilment()]);
        $this->fulfilmentRepository->shouldReceive('markAllExported')->once();
        $this->formatStrategy->shouldReceive('generate')->andReturn('csv');
        $this->formatStrategy->shouldReceive('extension')->andReturn('csv');
        $this->transport->shouldReceive('upload')->once();

        $this->makeService()->export($batch);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fulfilmentRepository = Mockery::mock(ProductFulfilmentRepository::class);
        $this->formatStrategy = Mockery::mock(PrintExportFormatStrategy::class);
        $this->transport = Mockery::mock(PrintExportTransport::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->logger->shouldReceive('info')->byDefault();
        $this->logger->shouldReceive('error')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}