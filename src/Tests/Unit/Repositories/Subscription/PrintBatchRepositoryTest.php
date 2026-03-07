<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\PrintBatchStatus;
use App\Enums\Subscriptions\PrintExportFormat;
use App\Models\PrintBatch;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PrintBatchRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private PrintBatchRepository $repository;

    // =========================================================================
    // Setup
    // =========================================================================

    public function test_create_for_issue_delivery_persists_batch_with_queued_status(): void
    {
        $issueDelivery = $this->createIssueDelivery();

        $batch = $this->repository->createForIssueDelivery($issueDelivery->id);

        $this->assertDatabaseHas('print_batches', [
            'id' => $batch->id,
            'issue_delivery_id' => $issueDelivery->id,
            'status' => PrintBatchStatus::QUEUED->value,
            'territory_id' => null,
        ]);
    }

    // =========================================================================
    // createForIssueDelivery
    // =========================================================================

    public function test_create_for_issue_delivery_uses_csv_format_by_default(): void
    {
        $issueDelivery = $this->createIssueDelivery();

        $batch = $this->repository->createForIssueDelivery($issueDelivery->id);

        $this->assertDatabaseHas('print_batches', [
            'id' => $batch->id,
            'format' => PrintExportFormat::CSV->value,
        ]);
    }

    public function test_find_or_create_returns_existing_queued_batch_for_territory(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $territory = $this->createTerritory();

        $existing = PrintBatch::create([
            'issue_delivery_id' => $issueDelivery->id,
            'territory_id' => $territory->id,
            'status' => PrintBatchStatus::QUEUED->value,
            'format' => PrintExportFormat::CSV->value,
        ]);

        $result = $this->repository->findOrCreateForIssueDeliveryAndTerritory(
            $issueDelivery->id,
            $territory->id,
        );

        $this->assertSame($existing->id, $result->id);
        $this->assertDatabaseCount('print_batches', 1);
    }

    // =========================================================================
    // findOrCreateForIssueDeliveryAndTerritory — returns existing
    // =========================================================================

    public function test_find_or_create_returns_existing_exporting_batch(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $territory = $this->createTerritory();

        $existing = PrintBatch::create([
            'issue_delivery_id' => $issueDelivery->id,
            'territory_id' => $territory->id,
            'status' => PrintBatchStatus::BATCH_EXPORTING->value,
            'format' => PrintExportFormat::CSV->value,
        ]);

        $result = $this->repository->findOrCreateForIssueDeliveryAndTerritory(
            $issueDelivery->id,
            $territory->id,
        );

        $this->assertSame($existing->id, $result->id);
        $this->assertDatabaseCount('print_batches', 1);
    }

    public function test_find_or_create_creates_new_batch_when_none_exists(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $territory = $this->createTerritory();

        $result = $this->repository->findOrCreateForIssueDeliveryAndTerritory(
            $issueDelivery->id,
            $territory->id,
        );

        $this->assertDatabaseHas('print_batches', [
            'id' => $result->id,
            'issue_delivery_id' => $issueDelivery->id,
            'territory_id' => $territory->id,
            'status' => PrintBatchStatus::QUEUED->value,
        ]);
    }

    // =========================================================================
    // findOrCreateForIssueDeliveryAndTerritory — creates new
    // =========================================================================

    public function test_find_or_create_stores_null_territory_id_for_global_batch(): void
    {
        $issueDelivery = $this->createIssueDelivery();

        $result = $this->repository->findOrCreateForIssueDeliveryAndTerritory(
            $issueDelivery->id,
            null,
        );

        $this->assertDatabaseHas('print_batches', [
            'id' => $result->id,
            'territory_id' => null,
        ]);
    }

    public function test_find_or_create_creates_separate_batches_for_different_territories(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $wales = $this->createTerritory();
        $scotland = $this->createTerritory();

        $this->repository->findOrCreateForIssueDeliveryAndTerritory($issueDelivery->id, $wales->id);
        $this->repository->findOrCreateForIssueDeliveryAndTerritory($issueDelivery->id, $scotland->id);

        $this->assertDatabaseCount('print_batches', 2);
    }

    public function test_find_by_issue_delivery_returns_all_batches_for_delivery(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $other = $this->createIssueDelivery();

        PrintBatch::create(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintBatchStatus::QUEUED->value, 'format' => PrintExportFormat::CSV->value]);
        PrintBatch::create(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintBatchStatus::QUEUED->value, 'format' => PrintExportFormat::CSV->value]);
        PrintBatch::create(['issue_delivery_id' => $other->id, 'status' => PrintBatchStatus::QUEUED->value, 'format' => PrintExportFormat::CSV->value]);

        $result = $this->repository->findByIssueDelivery($issueDelivery->id);

        $this->assertCount(2, $result);
    }

    // =========================================================================
    // findByIssueDelivery
    // =========================================================================

    public function test_find_by_issue_delivery_returns_empty_collection_when_none_exist(): void
    {
        $issueDelivery = $this->createIssueDelivery();

        $result = $this->repository->findByIssueDelivery($issueDelivery->id);

        $this->assertCount(0, $result);
    }

    public function test_find_or_fail_returns_batch_when_found(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $batch = PrintBatch::create([
            'issue_delivery_id' => $issueDelivery->id,
            'status' => PrintBatchStatus::QUEUED->value,
            'format' => PrintExportFormat::CSV->value,
        ]);

        $result = $this->repository->findOrFail($batch->id);

        $this->assertSame($batch->id, $result->id);
    }

    // =========================================================================
    // findOrFail
    // =========================================================================

    public function test_find_or_fail_throws_when_batch_not_found(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/PrintBatch #999 not found/');

        $this->repository->findOrFail(999);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PrintBatchRepository();
    }
}