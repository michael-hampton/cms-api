<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Enums\Subscriptions\PrintExportFormat;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\PrintBatch;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\Printing\BatchBuilderService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class BatchBuilderServiceTest extends FunctionalTestCase
{
    private PrintBatchRepository|MockInterface $batchRepository;
    private PrintFulfillmentRepository|MockInterface $fulfilmentRepository;
    private BatchBuilderService $service;

    public function test_creates_one_batch_per_territory(): void
    {
        $issueDelivery = $this->makeIssueDelivery(1);

        // Simulate fulfilments grouped into two territories: 3 and 5
        $grouped = new Collection([
            3 => new Collection([$this->makeFulfilment(), $this->makeFulfilment()]),
            5 => new Collection([$this->makeFulfilment()]),
        ]);

        $this->fulfilmentRepository
            ->shouldReceive('findByIssueDeliveryGroupedByTerritory')
            ->once()
            ->with(1)
            ->andReturn($grouped);

        $batchA = $this->makeBatch(10);
        $batchB = $this->makeBatch(11);

        $this->batchRepository
            ->shouldReceive('findOrCreateForIssueDeliveryAndTerritory')
            ->once()
            ->with(1, 3, PrintExportFormat::CSV)
            ->andReturn($batchA);

        $this->batchRepository
            ->shouldReceive('findOrCreateForIssueDeliveryAndTerritory')
            ->once()
            ->with(1, 5, PrintExportFormat::CSV)
            ->andReturn($batchB);

        $batches = $this->service->buildBatches($issueDelivery);

        $this->assertCount(2, $batches);
    }

    private function makeIssueDelivery(int $id): IssueDelivery
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = $id;
        return $delivery;
    }

    private function makeFulfilment(): object
    {
        return new \stdClass();
    }

    private function makeBatch(int $id): PrintBatch
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->id = $id;
        return $batch;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_returns_empty_collection_when_no_fulfilments_exist(): void
    {
        $issueDelivery = $this->makeIssueDelivery(1);

        $this->fulfilmentRepository
            ->shouldReceive('findByIssueDeliveryGroupedByTerritory')
            ->once()
            ->andReturn(new Collection([]));

        $this->batchRepository->shouldNotReceive('findOrCreateForIssueDeliveryAndTerritory');

        $batches = $this->service->buildBatches($issueDelivery);

        $this->assertCount(0, $batches);
    }

    public function test_normalises_null_territory_key_for_global_batch(): void
    {
        $issueDelivery = $this->makeIssueDelivery(2);

        // groupBy returns '' for null territory_id values in some ORM implementations
        $grouped = new Collection([
            '' => new Collection([$this->makeFulfilment()]),
        ]);

        $this->fulfilmentRepository
            ->shouldReceive('findByIssueDeliveryGroupedByTerritory')
            ->once()
            ->andReturn($grouped);

        $batch = $this->makeBatch(20);

        $this->batchRepository
            ->shouldReceive('findOrCreateForIssueDeliveryAndTerritory')
            ->once()
            ->with(2, null, PrintExportFormat::CSV) // null — not '' — after normalisation
            ->andReturn($batch);

        $batches = $this->service->buildBatches($issueDelivery);

        $this->assertCount(1, $batches);
    }

    public function test_uses_csv_format_by_default(): void
    {
        $issueDelivery = $this->makeIssueDelivery(3);

        $grouped = new Collection([
            1 => new Collection([$this->makeFulfilment()]),
        ]);

        $this->fulfilmentRepository
            ->shouldReceive('findByIssueDeliveryGroupedByTerritory')
            ->andReturn($grouped);

        $this->batchRepository
            ->shouldReceive('findOrCreateForIssueDeliveryAndTerritory')
            ->once()
            ->with(3, 1, PrintExportFormat::CSV)
            ->andReturn($this->makeBatch(30));

        $this->service->buildBatches($issueDelivery);

        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->batchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->fulfilmentRepository = Mockery::mock(PrintFulfillmentRepository::class);
        $this->service = new BatchBuilderService($this->batchRepository, $this->fulfilmentRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}