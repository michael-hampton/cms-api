<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Enums\Subscriptions\PrintExportFormat;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\PrintBatch;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\Printing\BatchBuilderService;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class BatchBuilderServiceTest extends UnitTestCase
{
    private PrintBatchRepository|MockInterface $batchRepository;
    private PrintFulfillmentRepository|MockInterface $fulfilmentRepository;
    private BatchBuilderService $service;

    protected function setUp(): void
    {
        $this->batchRepository = Mockery::mock(PrintBatchRepository::class);
        $this->fulfilmentRepository = Mockery::mock(PrintFulfillmentRepository::class);
        $this->service = new BatchBuilderService($this->batchRepository, $this->fulfilmentRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

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

    // =========================================================================
    // Territory override path
    // =========================================================================

    public function test_skips_territory_grouping_and_produces_single_batch_when_override_set(): void
    {
        $issueDelivery = $this->makeIssueDelivery(id: 10, territoryId: 7);

        // Fulfilment repository must NOT be consulted — override bypasses grouping entirely.
        $this->fulfilmentRepository->shouldNotReceive('findByIssueDeliveryGroupedByTerritory');

        $batch = $this->makeBatch(50);

        $this->batchRepository
            ->shouldReceive('findOrCreateForIssueDeliveryAndTerritory')
            ->once()
            ->with(10, 7, PrintExportFormat::CSV)
            ->andReturn($batch);

        $batches = $this->service->buildBatches($issueDelivery);

        $this->assertCount(1, $batches);
        $this->assertSame(50, $batches->first()->id);
    }


    public function test_override_batch_uses_issue_delivery_territory_id(): void
    {
        $issueDelivery = $this->makeIssueDelivery(id: 20, territoryId: 99);

        $this->fulfilmentRepository->shouldNotReceive('findByIssueDeliveryGroupedByTerritory');

        $this->batchRepository
            ->shouldReceive('findOrCreateForIssueDeliveryAndTerritory')
            ->once()
            ->with(20, 99, PrintExportFormat::CSV)
            ->andReturn($this->makeBatch(60));

        $this->service->buildBatches($issueDelivery);

        $this->assertTrue(true);
    }

    public function test_override_path_respects_explicit_format_argument(): void
    {
        $issueDelivery = $this->makeIssueDelivery(id: 30, territoryId: 5);

        $this->fulfilmentRepository->shouldNotReceive('findByIssueDeliveryGroupedByTerritory');

        $this->batchRepository
            ->shouldReceive('findOrCreateForIssueDeliveryAndTerritory')
            ->once()
            ->with(30, 5, PrintExportFormat::CSV)
            ->andReturn($this->makeBatch(70));

        // Even if more formats are added later, the API contract is explicit.
        $this->service->buildBatches($issueDelivery, PrintExportFormat::CSV);

        $this->assertTrue(true);
    }

    public function test_non_override_delivery_still_uses_grouped_path(): void
    {
        // Explicit null territory_id must use the normal grouped path.
        $issueDelivery = $this->makeIssueDelivery(id: 40, territoryId: null);

        $this->fulfilmentRepository
            ->shouldReceive('findByIssueDeliveryGroupedByTerritory')
            ->once()
            ->andReturn(new Collection([]));

        $this->batchRepository->shouldNotReceive('findOrCreateForIssueDeliveryAndTerritory');

        $batches = $this->service->buildBatches($issueDelivery);

        $this->assertCount(0, $batches);
    }

    // =========================================================================
    // Helpers
    // =========================================================================
    private function makeIssueDelivery(int $id, ?int $territoryId = null): IssueDelivery
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = $id;
        $delivery->territory_id = $territoryId;
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
}