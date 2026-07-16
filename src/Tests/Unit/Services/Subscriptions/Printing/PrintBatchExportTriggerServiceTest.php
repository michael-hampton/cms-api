<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\PendingDispatch;
use App\Jobs\Subscriptions\ExportPrintBatchJob;
use App\Models\PrintBatch;
use App\Services\Subscriptions\Printing\PrintBatchExportTriggerService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PrintBatchExportTriggerServiceTest extends TestCase
{
    private Dispatcher|MockInterface $dispatcher;
    private PendingDispatch|MockInterface $pendingDispatch;
    private PrintBatchExportTriggerService $service;

    public function test_dispatches_export_print_batch_job_on_the_print_queue(): void
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = 42;
        $batch->issue_delivery_id = 7;

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(
                fn($job) => $job instanceof ExportPrintBatchJob
                    && $this->extractPrivate($job, 'batchId') === 42
                    && $this->extractPrivate($job, 'issueDeliveryId') === 7
            ))
            ->andReturn($this->pendingDispatch);

        $this->pendingDispatch
            ->shouldReceive('onQueue')
            ->once()
            ->with('print')
            ->andReturnSelf();

        $this->service->trigger($batch);

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

        $this->service = new PrintBatchExportTriggerService($this->dispatcher);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
