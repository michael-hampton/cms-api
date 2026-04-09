<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\LabelRunStatus;
use App\Framework\Container;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\GenerateLabelJob;
use App\Models\LabelRun;
use App\Repositories\Subscriptions\LabelRunRepository;
use App\Services\Subscriptions\Printing\Label\LabelGenerationService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class GenerateLabelJobTest extends TestCase
{
    private MockInterface $labelRunRepository;
    private MockInterface $labelGenerationService;
    private MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->labelRunRepository = Mockery::mock(LabelRunRepository::class);
        $this->labelGenerationService = Mockery::mock(LabelGenerationService::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $container = Container::getInstance();
        $container->instance(LabelRunRepository::class, $this->labelRunRepository);
        $container->instance(LabelGenerationService::class, $this->labelGenerationService);
        $container->instance(Logger::class, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_delegates_to_label_generation_service(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending);

        $this->labelRunRepository->shouldReceive('find')->with(1)->andReturn($labelRun);

        $this->labelGenerationService
            ->shouldReceive('generate')
            ->once()
            ->with($labelRun);

        $job = GenerateLabelJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_it_returns_early_when_label_run_not_found(): void
    {
        $this->labelRunRepository->shouldReceive('find')->with(1)->andReturn(null);

        $this->labelGenerationService->shouldNotReceive('generate');

        $job = GenerateLabelJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_it_propagates_exceptions_from_generation_service_so_queue_can_retry(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending);

        $this->labelRunRepository->shouldReceive('find')->andReturn($labelRun);

        $this->labelGenerationService
            ->shouldReceive('generate')
            ->andThrow(new \RuntimeException('Transport failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transport failed');

        $job = GenerateLabelJob::for(1);
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeLabelRun(LabelRunStatus $status): MockInterface
    {
        $labelRun = Mockery::mock(LabelRun::class)->makePartial();
        $labelRun->id = 1;
        $labelRun->status = $status->value;
        return $labelRun;
    }
}