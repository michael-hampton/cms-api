<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\WorkflowStageResult;
use App\Enums\Subscriptions\PrintRunStatus;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\FulfilmentCompletionMonitorJob;
use App\Models\PrintRun;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Workflow\WorkflowRunRecorder;
use App\Services\Workflow\WorkflowRunRecorderFactory;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class FulfilmentCompletionMonitorJobTest extends TestCase
{
    private MockInterface $printRunRepository;
    private MockInterface $logger;
    private WorkflowRunRecorderFactory|MockInterface $recorderFactory;


    protected function setUp(): void
    {
        parent::setUp();
        $this->printRunRepository = Mockery::mock(PrintRunRepository::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->recorderFactory = Mockery::mock(WorkflowRunRecorderFactory::class)
            ->shouldIgnoreMissing();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_fires_stalled_event_when_print_run_still_fulfilling(): void
    {
        $printRun = $this->makePrintRun(
            status: PrintRunStatus::FULFILLING,
            totalChunks: 5,
            fulfilledChunks: 3,
        );

        $this->printRunRepository->shouldReceive('find')->with(1)->andReturn($printRun);

        $recorder = $this->makeRecorder();
        $recorder->shouldReceive('record')
            ->once()
            ->with(Mockery::on(fn($r) => $r instanceof WorkflowStageResult
                && $r->status === \App\Enums\Workflow\WorkflowStageStatus::FAILED
                && str_contains($r->error, '5')
            ));

        $this->recorderFactory
            ->shouldReceive('forPrintRun')
            ->with($printRun, 'phase_1', WorkflowRunStatus::STALLED)
            ->andReturn($recorder);

        $this->makeJob()->handle(1);

        $this->assertTrue(true);

    }

    public function test_it_does_not_fire_stalled_event_when_print_run_has_moved_past_fulfilling(): void
    {
        $printRun = $this->makePrintRun(
            status: PrintRunStatus::BATCHING,
            totalChunks: 5,
            fulfilledChunks: 5,
        );

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);

        $this->makeJob()->handle(1);

        $this->assertTrue(true);

    }

    public function test_it_returns_early_when_print_run_not_found(): void
    {
        $this->printRunRepository->shouldReceive('find')->andReturn(null);

        $this->makeJob()->handle(1);

        $this->assertTrue(true);
    }

    public function test_it_does_not_fire_stalled_event_for_completed_print_run(): void
    {
        $printRun = $this->makePrintRun(
            status: PrintRunStatus::COMPLETE,
            totalChunks: 5,
            fulfilledChunks: 5,
        );

        $this->printRunRepository->shouldReceive('find')->andReturn($printRun);

        $this->makeJob()->handle(1);

        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeJob(): FulfilmentCompletionMonitorJob
    {
        return new FulfilmentCompletionMonitorJob(
            $this->printRunRepository,
            $this->recorderFactory,
            $this->logger
        );
    }

    private function makePrintRun(
        PrintRunStatus $status,
        int            $totalChunks,
        int            $fulfilledChunks,
    ): MockInterface
    {
        $printRun = Mockery::mock(PrintRun::class)->makePartial();

        $printRun->id = 1;
        $printRun->status = $status->value;
        $printRun->total_chunks = $totalChunks;
        $printRun->fulfilled_chunks_count = $fulfilledChunks;

        $printRun->shouldReceive('isFulfilling')
            ->andReturn($status === PrintRunStatus::FULFILLING);

        return $printRun;
    }

    private function makeRecorder(): WorkflowRunRecorder|MockInterface
    {
        return Mockery::mock(WorkflowRunRecorder::class);
    }
}