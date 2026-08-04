<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Events\Subscriptions\AdHocFulfilmentFileRequested;
use App\Framework\Database\Database;
use App\Models\AdHocFulfilmentRequest;
use App\Models\PrintBatch;
use App\Repositories\Subscriptions\AdHocFulfilmentRequestRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Services\Subscriptions\Printing\AdHocFulfilmentGenerationService;
use App\Services\Subscriptions\Printing\PrintBatchExportTriggerService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Support\CapturingEventDispatcher;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

class AdHocFulfilmentGenerationServiceTest extends FunctionalTestCase
{
    private MockInterface $requestRepository;
    private MockInterface $printBatchRepository;
    private MockInterface $exportTriggerService;
    private MockInterface $databaseMock;
    private CapturingEventDispatcher $events;
    private AdHocFulfilmentGenerationService $service;

    public function test_generates_request_and_dispatches_export_within_a_transaction(): void
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = 42;
        $batch->status = 'pending';

        $request = Mockery::mock(AdHocFulfilmentRequest::class)->makePartial();
        $request->id = 7;

        $this->printBatchRepository->shouldReceive('find')->once()->with(42)->andReturn($batch);

        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $this->requestRepository->shouldReceive('createForPrintBatch')
            ->once()
            ->with(42, 99)
            ->andReturn($request);

        $this->exportTriggerService->shouldReceive('trigger')->once()->with($batch);

        $result = $this->service->generateForPrintBatch(42, 99);

        $this->assertSame($request, $result);
        $this->events->assertDispatched(
            AdHocFulfilmentFileRequested::class,
            fn(AdHocFulfilmentFileRequested $event): bool => $event->request === $request
        );
    }

    public function test_throws_when_print_batch_does_not_exist(): void
    {
        $this->printBatchRepository->shouldReceive('find')->once()->with(404)->andReturnNull();

        $this->databaseMock->shouldNotReceive('transaction');
        $this->requestRepository->shouldNotReceive('createForPrintBatch');
        $this->exportTriggerService->shouldNotReceive('trigger');

        $this->expectException(InvalidArgumentException::class);

        $this->service->generateForPrintBatch(404, 99);

        $this->events->assertNotDispatched(AdHocFulfilmentFileRequested::class);
    }

    public function test_throws_and_does_not_dispatch_when_batch_cannot_be_exported(): void
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = 42;
        $batch->status = \App\Enums\Subscriptions\PrintBatchStatus::BATCH_EXPORTED->value;

        $this->printBatchRepository->shouldReceive('find')->once()->with(42)->andReturn($batch);

        $this->databaseMock->shouldNotReceive('transaction');
        $this->requestRepository->shouldNotReceive('createForPrintBatch');
        $this->exportTriggerService->shouldNotReceive('trigger');

        $this->expectException(RuntimeException::class);

        $this->service->generateForPrintBatch(42, 99);

        $this->events->assertNotDispatched(AdHocFulfilmentFileRequested::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestRepository = Mockery::mock(AdHocFulfilmentRequestRepository::class);
        $this->printBatchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->exportTriggerService = Mockery::mock(PrintBatchExportTriggerService::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->events = CapturingEventDispatcher::fake();

        $this->service = new AdHocFulfilmentGenerationService(
            $this->requestRepository,
            $this->printBatchRepository,
            $this->exportTriggerService,
            $this->databaseMock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}