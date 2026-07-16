<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Printing\Label;

use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\PendingDispatch;
use App\Jobs\Subscriptions\GenerateLabelJob;
use App\Models\LabelRun;
use App\Services\Subscriptions\Printing\Label\LabelRunTriggerService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class LabelRunTriggerServiceTest extends TestCase
{
    private Dispatcher|MockInterface $dispatcher;
    private PendingDispatch|MockInterface $pendingDispatch;
    private LabelRunTriggerService $service;

    public function test_dispatches_generate_label_job_on_the_print_queue(): void
    {
        $labelRun = Mockery::mock(LabelRun::class)->makePartial();
        $labelRun->id = 123;

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(
                fn($job) => $job instanceof GenerateLabelJob
                    && $this->extractPrivate($job, 'labelRunId') === 123
            ))
            ->andReturn($this->pendingDispatch);

        $this->pendingDispatch
            ->shouldReceive('onQueue')
            ->once()
            ->with('print')
            ->andReturnSelf();

        $this->service->trigger($labelRun);

        $this->assertTrue(true);
    }

    private function extractPrivate(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = Mockery::mock(Dispatcher::class);
        $this->pendingDispatch = Mockery::mock(PendingDispatch::class);

        $this->service = new LabelRunTriggerService($this->dispatcher);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
