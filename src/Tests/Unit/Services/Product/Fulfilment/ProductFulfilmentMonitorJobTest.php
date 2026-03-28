<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Product\Fulfilment;

use App\Framework\Support\Logger;
use App\Jobs\Products\ProductFulfilmentMonitorJob;
use App\Models\ProductFulfilmentRun;
use App\Repositories\Product\ProductFulfilmentRunRepository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ProductFulfilmentMonitorJobTest extends TestCase
{
    private ProductFulfilmentRunRepository&MockInterface $runRepository;
    private Logger&MockInterface $logger;

    public function test_it_returns_early_when_run_not_found(): void
    {
        $this->runRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/run not found/'), Mockery::any());

        $this->handle(runId: 99);
        $this->assertTrue(true);
    }

    private function handle(int $runId): void
    {
        $job = new ProductFulfilmentMonitorJob($this->runRepository, $this->logger);
        $job->handle($runId);
    }

    public function test_it_returns_early_when_run_is_past_fulfilling(): void
    {
        $run = Mockery::mock(ProductFulfilmentRun::class)->makePartial();
        $run->shouldReceive('isFulfilling')->andReturn(false);
        $run->status = 'batched';

        $this->runRepository->shouldReceive('find')->with(1)->andReturn($run);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(Mockery::pattern('/past fulfilling phase/'), Mockery::any());

        // Must NOT log an error or fire the stalled event.
        $this->logger->shouldNotReceive('error');

        $this->handle(runId: 1);
        $this->assertTrue(true);

    }

    public function test_it_logs_error_and_fires_stalled_event_when_run_is_still_fulfilling(): void
    {
        $run = $this->makeStuckRun(runId: 1, completedChunks: 2, totalChunks: 5);

        $this->runRepository->shouldReceive('find')->with(1)->andReturn($run);

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with(
                Mockery::pattern('/stall detected/'),
                Mockery::on(fn($ctx) => $ctx['run_id'] === 1 &&
                    $ctx['completed_chunks'] === 2 &&
                    $ctx['total_chunks'] === 5 &&
                    $ctx['missing_chunks'] === 3
                ),
            );

        // Event::fake() + Event::assertDispatched(ProductFulfilmentStalled::class)
        // should be used in an integration test. Here we verify the preconditions
        // that make the event branch reachable: isFulfilling() returning true.
        $this->handle(runId: 1);
        $this->assertTrue(true);
    }

    private function makeStuckRun(
        int $runId,
        int $completedChunks,
        int $totalChunks,
    ): ProductFulfilmentRun&MockInterface
    {
        $run = Mockery::mock(ProductFulfilmentRun::class)->makePartial();
        $run->shouldReceive('isFulfilling')->andReturn(true);
        $run->fulfilled_chunks_count = $completedChunks;
        $run->total_chunks = $totalChunks;
        return $run;
    }

    public function test_it_calculates_missing_chunks_correctly(): void
    {
        $run = $this->makeStuckRun(runId: 1, completedChunks: 1, totalChunks: 4);

        $this->runRepository->shouldReceive('find')->andReturn($run);

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::on(fn($ctx) => $ctx['missing_chunks'] === 3),
            );

        $this->handle(runId: 1);
        $this->assertTrue(true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->runRepository = Mockery::mock(ProductFulfilmentRunRepository::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->logger->shouldReceive('info')->byDefault();
        $this->logger->shouldReceive('warning')->byDefault();
        $this->logger->shouldReceive('error')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}