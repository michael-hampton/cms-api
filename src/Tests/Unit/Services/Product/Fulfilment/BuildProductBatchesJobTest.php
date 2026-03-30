<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Product\Fulfilment;

use App\Framework\Support\Logger;
use App\Jobs\Products\BuildProductBatchesJob;
use App\Models\ProductBatch;
use App\Models\ProductFulfilment;
use App\Models\ProductFulfilmentRun;
use App\Repositories\Product\ProductBatchRepository;
use App\Repositories\Product\ProductFulfilmentRepository;
use App\Repositories\Product\ProductFulfilmentRunRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class BuildProductBatchesJobTest extends FunctionalTestCase
{
    private ProductFulfilmentRunRepository&MockInterface $runRepository;
    private ProductFulfilmentRepository&MockInterface $fulfilmentRepository;
    private ProductBatchRepository&MockInterface $batchRepository;
    private Logger&MockInterface $logger;

    public function test_it_groups_fulfilments_by_territory_and_creates_one_batch_per_group(): void
    {
        $run = $this->makeRun(1);

        // Two territories: 3 and null (global).
        $grouped = collect([
            '3' => collect([$this->makeFulfilment()]),
            '' => collect([$this->makeFulfilment(), $this->makeFulfilment()]),
        ]);

        $this->runRepository->shouldReceive('find')->with(1)->andReturn($run);
        $this->fulfilmentRepository->shouldReceive('findByRunGroupedByTerritory')->with(1)->andReturn($grouped);

        $this->batchRepository
            ->shouldReceive('findOrCreateForRunAndTerritory')
            ->once()
            ->with(1, 3)
            ->andReturn($this->makeBatch(10));

        $this->batchRepository
            ->shouldReceive('findOrCreateForRunAndTerritory')
            ->once()
            ->with(1, null)  // '' normalised to null
            ->andReturn($this->makeBatch(11));

        $run->shouldReceive('markBatching')->once();
        $run->shouldReceive('markBatched')->once();

        $this->handle(runId: 1);
        $this->assertTrue(true);
    }

    private function makeRun(int $id): ProductFulfilmentRun&MockInterface
    {
        $run = Mockery::mock(ProductFulfilmentRun::class)->makePartial();
        $run->id = $id;
        $run->shouldReceive('isComplete')->andReturn(false)->byDefault();
        $run->shouldReceive('isCancelled')->andReturn(false)->byDefault();
        $run->shouldReceive('markBatching')->byDefault();
        $run->shouldReceive('markBatched')->byDefault();
        return $run;
    }

    private function makeFulfilment(): ProductFulfilment&MockInterface
    {
        return Mockery::mock(ProductFulfilment::class);
    }

    private function makeBatch(int $id): ProductBatch&MockInterface
    {
        $batch = Mockery::mock(ProductBatch::class)->makePartial();
        $batch->id = $id;
        return $batch;
    }

    private function handle(int $runId): void
    {
        $job = new BuildProductBatchesJob(
            $this->runRepository,
            $this->fulfilmentRepository,
            $this->batchRepository,
            $this->logger,
        );
        $job->handle(
            $runId
        );
    }

    public function test_it_transitions_run_through_batching_then_batched(): void
    {
        $run = $this->makeRun(1);

        $this->runRepository->shouldReceive('find')->andReturn($run);
        $this->fulfilmentRepository->shouldReceive('findByRunGroupedByTerritory')->andReturn(collect([]));

        $callOrder = [];
        $run->shouldReceive('markBatching')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'batching';
        });
        $run->shouldReceive('markBatched')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'batched';
        });

        $this->handle(runId: 1);

        $this->assertSame(['batching', 'batched'], $callOrder);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function test_it_returns_early_when_run_not_found(): void
    {
        $this->runRepository->shouldReceive('find')->andReturn(null);
        $this->fulfilmentRepository->shouldNotReceive('findByRunGroupedByTerritory');

        $this->handle(runId: 99);
        $this->assertTrue(true);
    }

    public function test_it_skips_when_run_is_in_terminal_state(): void
    {
        $run = Mockery::mock(ProductFulfilmentRun::class)->makePartial();
        $run->id = 1;
        $run->shouldReceive('isComplete')->andReturn(true);
        $run->shouldReceive('isCancelled')->andReturn(false);

        $this->runRepository->shouldReceive('find')->andReturn($run);
        $this->fulfilmentRepository->shouldNotReceive('findByRunGroupedByTerritory');

        $this->handle(runId: 1);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->runRepository = Mockery::mock(ProductFulfilmentRunRepository::class);
        $this->fulfilmentRepository = Mockery::mock(ProductFulfilmentRepository::class);
        $this->batchRepository = Mockery::mock(ProductBatchRepository::class);
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


// ─────────────────────────────────────────────────────────────────────────────

namespace Tests\Unit\Jobs\Products;

use App\Framework\Support\Logger;
use App\Jobs\Products\ExportProductBatchJob;
use App\Models\ProductBatch;
use App\Repositories\Products\ProductBatchRepository;
use App\Services\Products\Fulfilment\ProductBatchExportService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ExportProductBatchJobTest extends TestCase
{
    private ProductBatchRepository&MockInterface $batchRepository;
    private ProductBatchExportService&MockInterface $exportService;
    private Logger&MockInterface $logger;

    /** @test */
    public function it_delegates_to_export_service(): void
    {
        $batch = Mockery::mock(ProductBatch::class);

        $this->batchRepository->shouldReceive('find')->with(5)->andReturn($batch);
        $this->exportService->shouldReceive('export')->once()->with($batch);

        $this->handle(batchId: 5);
    }

    private function handle(int $batchId): void
    {
        $job = new ExportProductBatchJob($batchId);
        $job->handle($this->batchRepository, $this->exportService, $this->logger);
    }

    /** @test */
    public function it_returns_early_when_batch_not_found(): void
    {
        $this->batchRepository->shouldReceive('find')->with(99)->andReturn(null);
        $this->exportService->shouldNotReceive('export');

        $this->logger->shouldReceive('error')->once()->with(
            Mockery::pattern('/batch not found/'),
            Mockery::any(),
        );

        $this->handle(batchId: 99);
    }

    /** @test */
    public function it_propagates_exceptions_from_export_service(): void
    {
        $batch = Mockery::mock(ProductBatch::class);
        $this->batchRepository->shouldReceive('find')->andReturn($batch);
        $this->exportService
            ->shouldReceive('export')
            ->andThrow(new \RuntimeException('transport failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('transport failed');

        $this->handle(batchId: 5);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->batchRepository = Mockery::mock(ProductBatchRepository::class);
        $this->exportService = Mockery::mock(ProductBatchExportService::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->logger->shouldReceive('error')->byDefault();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}