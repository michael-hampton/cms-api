<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Product\Fulfilment;

use App\Framework\Container;
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
        $job = BuildProductBatchesJob::for($runId);
        $job->__wakeup();
        $job->handle();
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

        $container = Container::getInstance();
        $container->instance(ProductFulfilmentRunRepository::class, $this->runRepository);
        $container->instance(ProductFulfilmentRepository::class, $this->fulfilmentRepository);
        $container->instance(ProductBatchRepository::class, $this->batchRepository);
        $container->instance(Logger::class, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}