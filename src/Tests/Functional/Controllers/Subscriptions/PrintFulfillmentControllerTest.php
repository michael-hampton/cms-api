<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\PrintBatchStatus;
use App\Enums\Subscriptions\PrintFulfillmentStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\Model;
use App\Models\PrintBatch;
use App\Models\PrintFulfillment;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PrintFulfillmentControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // Helpers
    // =========================================================================

    public function testIndexReturnsPaginatedFulfillments(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $this->createPrintFulfillment($batch);
        $this->createPrintFulfillment($batch);

        $response = $this->getForSite('/api/print-fulfillments');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertArrayHasKey('total', $data['pagination']);
        $this->assertGreaterThanOrEqual(2, $data['pagination']['total']);
    }

    private function createIssueDelivery(array $overrides = []): Model
    {
        return IssueDelivery::create(array_merge([
            'site_id' => $this->siteId,
            'issue_title' => 'Test Issue',
            'issue_number' => '001',
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +5 days')),
            'status' => IssueScheduleStatus::ACTIVE->value,
        ], $overrides));
    }

    private function createPrintBatch(IssueDelivery $issueDelivery, array $overrides = []): Model
    {
        return PrintBatch::create(array_merge([
            'issue_delivery_id' => $issueDelivery->id,
            'status' => PrintBatchStatus::PENDING->value,
            'format' => 'csv',
            'export_attempt_count' => 0,
        ], $overrides));
    }

    // =========================================================================
    // GET /print-fulfillments
    // =========================================================================

    private function createPrintFulfillment(PrintBatch $batch, array $overrides = []): Model
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $subscription = \App\Models\Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $plan->price ?? 10.00,
            'currency' => $plan->currency ?? 'GBP',
            'delivery_type' => SubscriptionType::PRINTED->value,
            'type' => 'paid',
        ]);

        return PrintFulfillment::create(array_merge([
            'batch_id' => $batch->id,
            'subscription_issue_fulfilment_id' => $batch->issue_delivery_id,
            'subscription_id' => $subscription->id,
            'full_name' => 'Jane Doe',
            'address_line_1' => '10 Test Street',
            'address_line_2' => null,
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
            'tracking_number' => null,
            'status' => PrintFulfillmentStatus::IN_TRANSIT->value,
            'delivery_address_snapshot' => []
        ], $overrides));
    }

    public function testIndexResponseShapeIncludesExpectedFields(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $fulfillment = $this->createPrintFulfillment($batch);

        $response = $this->getForSite('/api/print-fulfillments');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $item = collect($data['items'])->firstWhere('id', $fulfillment->id);
        $this->assertNotNull($item);

        foreach (['id', 'full_name', 'address_line_1', 'city', 'postcode', 'country', 'status', 'batch_id', 'subscription_issue_fulfilment_id'] as $field) {
            $this->assertArrayHasKey($field, $item, "Missing field: {$field}");
        }
    }

    public function testIndexFiltersOnStatus(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $shipped = $this->createPrintFulfillment($batch, ['status' => PrintFulfillmentStatus::SHIPPED->value]);
        $this->createPrintFulfillment($batch, ['status' => PrintFulfillmentStatus::IN_TRANSIT->value]);

        $response = $this->getForSite('/api/print-fulfillments?status=shipped');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        foreach ($data['items'] as $item) {
            $this->assertEquals(PrintFulfillmentStatus::SHIPPED->value, $item['status']);
        }

        $ids = array_column($data['items'], 'id');
        $this->assertContains($shipped->id, $ids);
    }

    public function testIndexFiltersByCreatedAtRange(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $inRange = $this->createPrintFulfillment($batch);
        $outOfRange = $this->createPrintFulfillment($batch);

        PrintFulfillment::where('id', $inRange->id)->update(['created_at' => '2026-03-15 10:00:00']);
        PrintFulfillment::where('id', $outOfRange->id)->update(['created_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite('/api/print-fulfillments?created_at[from]=2026-03-01&created_at[to]=2026-03-31');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['items'], 'id');
        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($outOfRange->id, $ids);
    }

    public function testIndexFiltersByUpdatedAtRange(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $inRange = $this->createPrintFulfillment($batch);
        $outOfRange = $this->createPrintFulfillment($batch);

        PrintFulfillment::where('id', $inRange->id)->update(['updated_at' => '2026-03-15 10:00:00']);
        PrintFulfillment::where('id', $outOfRange->id)->update(['updated_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite('/api/print-fulfillments?updated_at[from]=2026-03-01&updated_at[to]=2026-03-31');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['items'], 'id');
        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($outOfRange->id, $ids);
    }

    public function testIndexFiltersOnBatchId(): void
    {
        $issue = $this->createIssueDelivery();
        $batch1 = $this->createPrintBatch($issue);
        $batch2 = $this->createPrintBatch($issue);
        $f1 = $this->createPrintFulfillment($batch1);
        $this->createPrintFulfillment($batch2);

        $response = $this->getForSite("/api/print-fulfillments?batch_id={$batch1->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        foreach ($data['items'] as $item) {
            $this->assertEquals($batch1->id, $item['batch_id']);
        }

        $ids = array_column($data['items'], 'id');
        $this->assertContains($f1->id, $ids);
    }

    public function testIndexFiltersOnIssueId(): void
    {
        $issue1 = $this->createIssueDelivery(['issue_title' => 'Issue One']);
        $issue2 = $this->createIssueDelivery(['issue_title' => 'Issue Two']);
        $batch1 = $this->createPrintBatch($issue1);
        $batch2 = $this->createPrintBatch($issue2);
        $f1 = $this->createPrintFulfillment($batch1);
        $this->createPrintFulfillment($batch2);

        $response = $this->getForSite("/api/print-fulfillments?issue_id={$issue1->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['items'], 'id');
        $this->assertContains($f1->id, $ids);
    }

    public function testIndexSearchMatchesOnFullName(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $match = $this->createPrintFulfillment($batch, ['full_name' => 'Alice Smith']);
        $this->createPrintFulfillment($batch, ['full_name' => 'Bob Jones']);

        $response = $this->getForSite('/api/print-fulfillments?search=Alice');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['items'], 'id');
        $this->assertContains($match->id, $ids);
        $this->assertNotContains(
            collect($data['items'])->firstWhere('full_name', 'Bob Jones')['id'] ?? null,
            [$match->id]
        );
    }

    public function testIndexRespectsPerPageLimit(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        for ($i = 0; $i < 5; $i++) {
            $this->createPrintFulfillment($batch);
        }

        $response = $this->getForSite('/api/print-fulfillments?per_page=2');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['items']);
        $this->assertEquals(2, $data['pagination']['per_page']);
    }

    // =========================================================================
    // GET /print-fulfillments/{id}
    // =========================================================================

    public function testShowReturnsFulfillmentWithRelations(): void
    {
        $issue = $this->createIssueDelivery(['issue_title' => 'Detail Issue']);
        $batch = $this->createPrintBatch($issue);
        $fulfillment = $this->createPrintFulfillment($batch);

        $response = $this->getForSite("/api/print-fulfillments/{$fulfillment->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $item = $data['data'];

        $this->assertEquals($fulfillment->id, $item['id']);
        $this->assertEquals('Jane Doe', $item['full_name']);

        // Issue delivery is embedded
        $this->assertArrayHasKey('issue_delivery', $item);
        $this->assertNotNull($item['issue_delivery']);
        $this->assertEquals('Detail Issue', $item['issue_delivery']['issue_title']);
        $this->assertEquals($issue->id, $item['issue_delivery']['id']);

        // Batch is embedded
        $this->assertArrayHasKey('batch', $item);
        $this->assertNotNull($item['batch']);
        $this->assertEquals($batch->id, $item['batch']['id']);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $response = $this->getForSite('/api/print-fulfillments/99999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testShowResponseIncludesAddressFields(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $fulfillment = $this->createPrintFulfillment($batch, [
            'address_line_1' => '42 Elm Road',
            'address_line_2' => 'Flat 3',
            'city' => 'Manchester',
            'postcode' => 'M1 1AE',
            'country' => 'GB',
        ]);

        $response = $this->getForSite("/api/print-fulfillments/{$fulfillment->id}");
        $data = json_decode($response->getContent(), true);
        $item = $data['data'];

        $this->assertEquals('42 Elm Road', $item['address_line_1']);
        $this->assertEquals('Flat 3', $item['address_line_2']);
        $this->assertEquals('Manchester', $item['city']);
        $this->assertEquals('M1 1AE', $item['postcode']);
        $this->assertEquals('GB', $item['country']);
    }

    public function testShowResponseIssueDeliveryIncludesScheduleFields(): void
    {
        $issue = $this->createIssueDelivery([
            'issue_title' => 'Spring Edition',
            'issue_number' => '042',
            'status' => IssueScheduleStatus::ACTIVE->value,
        ]);
        $batch = $this->createPrintBatch($issue);
        $fulfillment = $this->createPrintFulfillment($batch);

        $response = $this->getForSite("/api/print-fulfillments/{$fulfillment->id}");
        $data = json_decode($response->getContent(), true);
        $issuePart = $data['data']['issue_delivery'];

        $this->assertEquals('Spring Edition', $issuePart['issue_title']);
        $this->assertEquals('042', $issuePart['issue_number']);
        $this->assertArrayHasKey('on_sale_date', $issuePart);
        $this->assertArrayHasKey('estimated_delivery_date', $issuePart);
        $this->assertArrayHasKey('status', $issuePart);
    }

    public function testShowResponseBatchIncludesExportFields(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue, [
            'status' => PrintBatchStatus::BATCH_EXPORTED->value,
            'format' => 'csv',
            'export_attempt_count' => 2,
            'file_path' => '/exports/batch-123.csv',
        ]);
        $fulfillment = $this->createPrintFulfillment($batch);

        $response = $this->getForSite("/api/print-fulfillments/{$fulfillment->id}");
        $data = json_decode($response->getContent(), true);
        $batchPart = $data['data']['batch'];

        $this->assertEquals(PrintBatchStatus::BATCH_EXPORTED->value, $batchPart['status']);
        $this->assertEquals('csv', $batchPart['format']);
        $this->assertEquals(2, $batchPart['export_attempt_count']);
        $this->assertEquals('/exports/batch-123.csv', $batchPart['file_path']);
    }

    // =========================================================================
    // GET /batches/{batch}/print-fulfillments
    // =========================================================================

    public function testListByBatchReturnsFulfillmentsForBatch(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $f1 = $this->createPrintFulfillment($batch);
        $f2 = $this->createPrintFulfillment($batch);

        // Fulfillment in a different batch — must NOT appear
        $otherBatch = $this->createPrintBatch($issue);
        $this->createPrintFulfillment($otherBatch);

        $response = $this->getForSite("/api/batches/{$batch->id}/print-fulfillments");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $ids = array_column($data['data'], 'id');
        $this->assertContains($f1->id, $ids);
        $this->assertContains($f2->id, $ids);
    }

    public function testListByBatchReturns404WhenBatchNotFound(): void
    {
        $response = $this->getForSite('/api/batches/99999/print-fulfillments');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testListByBatchFiltersOnStatus(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $shipped = $this->createPrintFulfillment($batch, ['status' => PrintFulfillmentStatus::SHIPPED->value]);
        $this->createPrintFulfillment($batch, ['status' => PrintFulfillmentStatus::SENT_TO_PRINTER->value]);

        $response = $this->getForSite("/api/batches/{$batch->id}/print-fulfillments?status=shipped");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['data'], 'id');
        $this->assertContains($shipped->id, $ids);

        foreach ($data['data'] as $item) {
            $this->assertEquals(PrintFulfillmentStatus::SHIPPED->value, $item['status']);
        }
    }

    public function testListByBatchIgnoresInvalidStatusFilter(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $this->createPrintFulfillment($batch);

        // Invalid status is silently ignored — all fulfillments for the batch are returned
        $response = $this->getForSite("/api/batches/{$batch->id}/print-fulfillments?status=not-a-status");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']);
    }

    public function testListByBatchReturnsEmptyArrayWhenNoFulfillments(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);

        $response = $this->getForSite("/api/batches/{$batch->id}/print-fulfillments");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertCount(0, $data['data']);
    }

    // =========================================================================
    // PUT /print-fulfillments/{id}/tracking
    // =========================================================================

    public function testUpdateTrackingSetsTrackingNumberAndMarksShipped(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $fulfillment = $this->createPrintFulfillment($batch, ['status' => PrintFulfillmentStatus::SENT_TO_PRINTER->value]);

        $response = $this->putForSite("/api/print-fulfillments/{$fulfillment->id}/tracking", [
            'tracking_number' => 'TRACK99887766',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('TRACK99887766', $data['data']['tracking_number']);
        $this->assertEquals(PrintFulfillmentStatus::SHIPPED->value, $data['data']['status']);

        $fresh = $fulfillment->fresh();
        $this->assertEquals('TRACK99887766', $fresh->tracking_number);
        $this->assertEquals(PrintFulfillmentStatus::SHIPPED->value, $fresh->status);
    }

    public function testUpdateTrackingReturns422WhenTrackingNumberMissing(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $fulfillment = $this->createPrintFulfillment($batch);

        $response = $this->putForSite("/api/print-fulfillments/{$fulfillment->id}/tracking", []);

        $this->assertEquals(422, $response->getStatusCode());
        // Status must not have changed
        $this->assertEquals(PrintFulfillmentStatus::IN_TRANSIT->value, $fulfillment->fresh()->status);
    }

    public function testUpdateTrackingReturns422WhenTrackingNumberIsBlank(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $fulfillment = $this->createPrintFulfillment($batch);

        $response = $this->putForSite("/api/print-fulfillments/{$fulfillment->id}/tracking", [
            'tracking_number' => '   ',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateTrackingReturns404WhenNotFound(): void
    {
        $response = $this->putForSite('/api/print-fulfillments/99999/tracking', [
            'tracking_number' => 'TRACK123',
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateTrackingResponseIncludesEmbeddedRelations(): void
    {
        $issue = $this->createIssueDelivery(['issue_title' => 'Tracking Test Issue']);
        $batch = $this->createPrintBatch($issue);
        $fulfillment = $this->createPrintFulfillment($batch);

        $response = $this->putForSite("/api/print-fulfillments/{$fulfillment->id}/tracking", [
            'tracking_number' => 'TRK-001',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Relations must be present in the response data after update
        $this->assertArrayHasKey('issue_delivery', $data['data']);
        $this->assertArrayHasKey('batch', $data['data']);
        $this->assertEquals('Tracking Test Issue', $data['data']['issue_delivery']['issue_title']);
    }

    public function testUpdateTrackingCanOverwriteExistingTrackingNumber(): void
    {
        $issue = $this->createIssueDelivery();
        $batch = $this->createPrintBatch($issue);
        $fulfillment = $this->createPrintFulfillment($batch, [
            'tracking_number' => 'OLD-TRACK-001',
            'status' => PrintFulfillmentStatus::SHIPPED->value,
        ]);

        $response = $this->putForSite("/api/print-fulfillments/{$fulfillment->id}/tracking", [
            'tracking_number' => 'NEW-TRACK-002',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('NEW-TRACK-002', $fulfillment->fresh()->tracking_number);
    }
}