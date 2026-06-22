<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\PrintBatchStatus;
use App\Enums\Subscriptions\PrintExportFormat;
use App\Enums\Subscriptions\PrintFulfillmentStatus;
use App\Models\Model;
use App\Models\PrintBatch;
use App\Models\PrintFulfillment;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PrintFulfillmentRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private PrintFulfillmentRepository $repository;

    public function test_create_persists_fulfilment_with_all_required_fields(): void
    {
        [$batch, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();

        $result = $this->repository->createFullfilment(
            batchId: $batch->id,
            subscriptionIssueFulfilmentId: $subscriptionIssueFulfilment->id,
            subscriptionId: $subscription->id,
            fullName: 'Jane Doe',
            addressSnapshot: ['address_line_1' => '1 Test St'],
            addressLine1: '1 Test St',
            addressLine2: 'Flat 2',
            city: 'Cardiff',
            postcode: 'CF10 3NQ',
            country: 'GB',
        );

        $this->assertDatabaseHas('print_fulfillments', [
            'id' => $result->id,
            'batch_id' => $batch->id,
            'subscription_id' => $subscription->id,
            'full_name' => 'Jane Doe',
            'address_line_1' => '1 Test St',
            'address_line_2' => 'Flat 2',
            'city' => 'Cardiff',
            'postcode' => 'CF10 3NQ',
            'country' => 'GB',
            'territory_id' => null,
            'status' => PrintFulfillmentStatus::QUEUED->value,
        ]);
    }

    // =========================================================================
    // create
    // =========================================================================

    /**
     * Create a PrintBatch + SubscriptionIssueFulfilment + Subscription in one call.
     * Returns [$batch, $subscriptionIssueFulfilment, $subscription].
     */
    private function makePrerequisites(): array
    {
        $issueDelivery = $this->createIssueDelivery();
        $subscription = $this->createSubscription();

        $batch = PrintBatch::create([
            'issue_delivery_id' => $issueDelivery->id,
            'status' => PrintBatchStatus::QUEUED->value,
            'format' => PrintExportFormat::CSV->value,
        ]);

        $subscriptionIssueFulfilment = \App\Models\SubscriptionIssueFulfilment::create([
            'issue_delivery_id' => $issueDelivery->id,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);

        return [$batch, $subscriptionIssueFulfilment, $subscription];
    }

    public function test_create_stores_territory_id_when_provided(): void
    {
        [$batch, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();
        $territory = $this->createTerritory();

        $result = $this->repository->createFullfilment(
            batchId: $batch->id,
            subscriptionIssueFulfilmentId: $subscriptionIssueFulfilment->id,
            subscriptionId: $subscription->id,
            fullName: 'John Smith',
            addressSnapshot: [],
            addressLine1: '2 High St',
            addressLine2: null,
            city: 'Edinburgh',
            postcode: 'EH1 1AA',
            country: 'GB',
            territoryId: $territory->id,
        );

        $this->assertDatabaseHas('print_fulfillments', [
            'id' => $result->id,
            'territory_id' => $territory->id,
        ]);
    }

    public function test_create_sets_queued_status(): void
    {
        [$batch, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();

        $result = $this->repository->createFullfilment(
            batchId: $batch->id,
            subscriptionIssueFulfilmentId: $subscriptionIssueFulfilment->id,
            subscriptionId: $subscription->id,
            fullName: 'A B',
            addressSnapshot: [],
            addressLine1: '1 St',
            addressLine2: null,
            city: 'Cardiff',
            postcode: 'CF1 1AA',
            country: 'GB',
        );

        $this->assertSame(PrintFulfillmentStatus::QUEUED->value, $result->status);
    }

    // =========================================================================
    // findByBatch
    // =========================================================================

    public function test_find_by_batch_returns_only_fulfilments_for_that_batch(): void
    {
        [$batchA, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();
        [$batchB] = $this->makePrerequisites();

        $this->createFulfilment($batchA->id, $subscriptionIssueFulfilment->id, $subscription->id);
        $this->createFulfilment($batchA->id, $subscriptionIssueFulfilment->id, $subscription->id);
        $this->createFulfilment($batchB->id, $subscriptionIssueFulfilment->id, $subscription->id);

        $result = $this->repository->findByBatch($batchA->id);

        $this->assertCount(2, $result);
        foreach ($result as $fulfilment) {
            $this->assertSame($batchA->id, $fulfilment->batch_id);
        }
    }

    private function createFulfilment(
        int  $batchId,
        int  $subscriptionIssueFulfilmentId,
        int  $subscriptionId,
        ?int $territoryId = null,
    ): Model
    {
        return PrintFulfillment::create([
            'batch_id' => $batchId,
            'subscription_issue_fulfilment_id' => $subscriptionIssueFulfilmentId,
            'subscription_id' => $subscriptionId,
            'full_name' => 'Test User',
            'delivery_address_snapshot' => [],
            'address_line_1' => '1 Test St',
            'address_line_2' => null,
            'city' => 'Cardiff',
            'postcode' => 'CF10 3NQ',
            'country' => 'GB',
            'territory_id' => $territoryId,
            'status' => PrintFulfillmentStatus::QUEUED->value,
        ]);
    }

    // =========================================================================
    // findByIssueDeliveryGroupedByTerritory
    // =========================================================================

    public function test_find_by_batch_returns_empty_array_when_none_found(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $batch = PrintBatch::create([
            'issue_delivery_id' => $issueDelivery->id,
            'status' => PrintBatchStatus::QUEUED->value,
            'format' => PrintExportFormat::CSV->value,
        ]);

        $result = $this->repository->findByBatch($batch->id);

        $this->assertSame([], $result);
    }

    public function test_grouped_by_territory_groups_fulfilments_by_territory_id(): void
    {
        [$batch, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();
        $wales = $this->createTerritory();
        $scotland = $this->createTerritory();

        $this->createFulfilment($batch->id, $subscriptionIssueFulfilment->id, $subscription->id, $wales->id);
        $this->createFulfilment($batch->id, $subscriptionIssueFulfilment->id, $subscription->id, $wales->id);
        $this->createFulfilment($batch->id, $subscriptionIssueFulfilment->id, $subscription->id, $scotland->id);

        $issueDelivery = \App\Models\IssueDelivery::find($this->getIssueDeliveryIdFromSubscriptionIssueFulfilment($subscriptionIssueFulfilment));
        $result = $this->repository->findByIssueDeliveryGroupedByTerritory($issueDelivery->id);

        $this->assertCount(2, $result);
    }

    // =========================================================================
    // existsForSubscriptionDeliveryAndTerritory
    // =========================================================================

    private function getIssueDeliveryIdFromSubscriptionIssueFulfilment(\App\Models\SubscriptionIssueFulfilment $subscriptionIssueFulfilment): int
    {
        return $subscriptionIssueFulfilment->issue_delivery_id;
    }

    public function test_grouped_by_territory_returns_empty_collection_when_no_fulfilments(): void
    {
        $issueDelivery = $this->createIssueDelivery();

        $result = $this->repository->findByIssueDeliveryGroupedByTerritory($issueDelivery->id);

        $this->assertCount(0, $result);
    }

    public function test_exists_returns_true_when_matching_fulfilment_found(): void
    {
        [$batch, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();
        $territory = $this->createTerritory();

        $this->createFulfilment($batch->id, $subscriptionIssueFulfilment->id, $subscription->id, $territory->id);

        $result = $this->repository->existsForSubscriptionDeliveryAndTerritory(
            $subscription->id,
            $subscriptionIssueFulfilment->id,
            $territory->id,
        );

        $this->assertTrue($result);
    }

    public function test_exists_returns_false_when_no_matching_fulfilment(): void
    {
        [$batch, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();

        $result = $this->repository->existsForSubscriptionDeliveryAndTerritory(
            $subscription->id,
            $subscriptionIssueFulfilment->id,
            null,
        );

        $this->assertFalse($result);
    }

    // =========================================================================
    // markAllExported
    // =========================================================================

    public function test_exists_does_not_match_different_territory(): void
    {
        [$batch, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();
        $wales = $this->createTerritory();
        $scotland = $this->createTerritory();

        $this->createFulfilment($batch->id, $subscriptionIssueFulfilment->id, $subscription->id, $wales->id);

        $result = $this->repository->existsForSubscriptionDeliveryAndTerritory(
            $subscription->id,
            $subscriptionIssueFulfilment->id,
            $scotland->id,
        );

        $this->assertFalse($result);
    }

    public function test_exists_matches_null_territory_for_global_edition(): void
    {
        [$batch, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();

        $this->createFulfilment($batch->id, $subscriptionIssueFulfilment->id, $subscription->id, null);

        $result = $this->repository->existsForSubscriptionDeliveryAndTerritory(
            $subscription->id,
            $subscriptionIssueFulfilment->id,
            null,
        );

        $this->assertTrue($result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_mark_all_exported_updates_status_for_all_fulfilments_in_batch(): void
    {
        [$batch, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();

        $this->createFulfilment($batch->id, $subscriptionIssueFulfilment->id, $subscription->id);
        $this->createFulfilment($batch->id, $subscriptionIssueFulfilment->id, $subscription->id);

        $this->repository->markAllExported($batch->id);

        $this->assertDatabaseMissing('print_fulfillments', [
            'batch_id' => $batch->id,
            'status' => PrintFulfillmentStatus::QUEUED->value,
        ]);

        $this->assertDatabaseHas('print_fulfillments', [
            'batch_id' => $batch->id,
            'status' => PrintFulfillmentStatus::EXPORTED->value,
        ]);
    }

    public function test_mark_all_exported_does_not_affect_other_batches(): void
    {
        [$batchA, $subscriptionIssueFulfilment, $subscription] = $this->makePrerequisites();
        [$batchB] = $this->makePrerequisites();

        $this->createFulfilment($batchA->id, $subscriptionIssueFulfilment->id, $subscription->id);
        $this->createFulfilment($batchB->id, $subscriptionIssueFulfilment->id, $subscription->id);

        $this->repository->markAllExported($batchA->id);

        $this->assertDatabaseHas('print_fulfillments', [
            'batch_id' => $batchB->id,
            'status' => PrintFulfillmentStatus::QUEUED->value,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PrintFulfillmentRepository();
    }
}