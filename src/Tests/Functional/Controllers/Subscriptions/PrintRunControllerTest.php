<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\PrintRunStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\Model;
use App\Models\PrintRun;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use PHPUnit\Framework\Attributes\DataProvider;

class PrintRunControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // Helpers
    // =========================================================================

    public static function nonCancellableStatuses(): array
    {
        return [
            [PrintRunStatus::COMPLETE->value],
            [PrintRunStatus::CANCELLED->value],
            [PrintRunStatus::FAILED->value],
        ];
    }

    public function testListByIssueReturnsPrintRunsForIssue(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $printRun = $this->createPrintRun($issueDelivery);

        $response = $this->getForSite("/api/issues/{$issueDelivery->id}/print-runs");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals($printRun->id, $data['data'][0]['id']);
        $this->assertEquals($issueDelivery->id, $data['data'][0]['issue_delivery_id']);
    }

    // =========================================================================
    // GET /issues/{issue}/print-runs
    // =========================================================================

    private function createIssueDelivery(array $overrides = []): Model
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        \App\Models\Subscription::create([
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

        return IssueDelivery::create(array_merge([
            'site_id' => $this->siteId,
            'issue_title' => 'Test Issue',
            'issue_number' => '001',
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +5 days')),
            'status' => IssueScheduleStatus::ACTIVE->value,
        ], $overrides));
    }

    private function createPrintRun(IssueDelivery $issueDelivery, array $overrides = []): Model
    {
        return PrintRun::create(array_merge([
            'issue_delivery_id' => $issueDelivery->id,
            'status' => PrintRunStatus::PENDING->value,
            'is_regional' => false,
            'driver_sync_enabled' => false,
        ], $overrides));
    }

    public function testListByIssueReturns404WhenIssueNotFound(): void
    {
        $response = $this->getForSite('/api/issues/99999/print-runs');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testListByIssueFiltersOnStatus(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $pending = $this->createPrintRun($issueDelivery, ['status' => PrintRunStatus::PENDING->value]);
        $this->createPrintRun($issueDelivery, ['status' => PrintRunStatus::COMPLETE->value]);

        $response = $this->getForSite("/api/issues/{$issueDelivery->id}/print-runs?status=pending");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']);
        $this->assertEquals($pending->id, $data['data'][0]['id']);
    }

    public function testListByIssueIgnoresInvalidStatusFilter(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $this->createPrintRun($issueDelivery);

        // Invalid status is silently ignored — all runs are returned
        $response = $this->getForSite("/api/issues/{$issueDelivery->id}/print-runs?status=not-a-status");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']);
    }

    // =========================================================================
    // GET /print-runs
    // =========================================================================

    public function testListByIssueReturnsEmptyArrayWhenNoPrintRuns(): void
    {
        $issueDelivery = $this->createIssueDelivery();

        $response = $this->getForSite("/api/issues/{$issueDelivery->id}/print-runs");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertCount(0, $data['data']);
    }

    public function testIndexReturnsPaginatedPrintRuns(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $this->createPrintRun($issueDelivery);
        $this->createPrintRun($issueDelivery);

        $response = $this->getForSite('/api/print-runs');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertArrayHasKey('total', $data['pagination']);
        $this->assertGreaterThanOrEqual(2, $data['pagination']['total']);
    }

    public function testIndexFiltersOnStatus(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $this->createPrintRun($issueDelivery, ['status' => PrintRunStatus::PENDING->value]);
        $this->createPrintRun($issueDelivery, ['status' => PrintRunStatus::COMPLETE->value]);

        $response = $this->getForSite('/api/print-runs?status=complete');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        foreach ($data['data'] as $item) {
            $this->assertEquals(PrintRunStatus::COMPLETE->value, $item['status']);
        }
    }

    public function testIndexRejects422ForInvalidStatus(): void
    {
        $response = $this->getForSite('/api/print-runs?status=invalid-status');

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testIndexFiltersOnIssueId(): void
    {
        $issue1 = $this->createIssueDelivery();
        $issue2 = $this->createIssueDelivery(['issue_title' => 'Other Issue']);
        $run1 = $this->createPrintRun($issue1);
        $this->createPrintRun($issue2);

        $response = $this->getForSite("/api/print-runs?issue_id={$issue1->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']);
        $this->assertEquals($run1->id, $data['data'][0]['id']);
    }

    public function testIndexFiltersOnDate(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $printRun = $this->createPrintRun($issueDelivery);
        $today = date('Y-m-d');

        $response = $this->getForSite("/api/print-runs?date={$today}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['data'], 'id');
        $this->assertContains($printRun->id, $ids);
    }

    // =========================================================================
    // GET /print-runs/{printRun}
    // =========================================================================

    public function testIndexMetaContainsExpectedKeys(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $this->createPrintRun($issueDelivery);

        $response = $this->getForSite('/api/print-runs');
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('total', $data['pagination']);
        $this->assertArrayHasKey('per_page', $data['pagination']);
        $this->assertArrayHasKey('current_page', $data['pagination']);
        $this->assertArrayHasKey('last_page', $data['pagination']);
    }

    public function testShowReturnsPrintRunDetail(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $printRun = $this->createPrintRun($issueDelivery, [
            'is_regional' => true,
            'driver_sync_enabled' => true,
        ]);

        $response = $this->getForSite("/api/print-runs/{$printRun->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($printRun->id, $data['data']['id']);
        $this->assertEquals($issueDelivery->id, $data['data']['issue_delivery_id']);
        $this->assertEquals(PrintRunStatus::PENDING->value, $data['data']['status']);
        $this->assertTrue($data['data']['is_regional']);
        $this->assertTrue($data['data']['driver_sync_enabled']);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $response = $this->getForSite('/api/print-runs/99999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    // =========================================================================
    // PUT /print-runs/{printRun}/cancel
    // =========================================================================

    public function testShowResponseContainsExpectedFields(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $printRun = $this->createPrintRun($issueDelivery);

        $response = $this->getForSite("/api/print-runs/{$printRun->id}");
        $data = json_decode($response->getContent(), true)['data'];

        foreach (['id', 'issue_delivery_id', 'status', 'is_regional', 'driver_sync_enabled', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $data, "Expected field '{$field}' missing from response");
        }
    }

    public function testCancelSucceedsWhenPending(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $printRun = $this->createPrintRun($issueDelivery, [
            'status' => PrintRunStatus::PENDING->value,
        ]);

        $response = $this->putForSite("/api/print-runs/{$printRun->id}/cancel", []);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(PrintRunStatus::CANCELLED->value, $data['data']['status']);
        $this->assertEquals(PrintRunStatus::CANCELLED->value, $printRun->fresh()->status);
    }

    #[DataProvider('nonCancellableStatuses')]
    public function testCancelRejects422WhenNotPending(string $status): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $printRun = $this->createPrintRun($issueDelivery, ['status' => $status]);

        $response = $this->putForSite("/api/print-runs/{$printRun->id}/cancel", []);

        $this->assertEquals(422, $response->getStatusCode());
        // Status must not have changed
        $this->assertEquals($status, $printRun->fresh()->status);
    }

    public function testCancelReturns404WhenNotFound(): void
    {
        $response = $this->putForSite('/api/print-runs/99999/cancel', []);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCancelResponseContainsUpdatedStatus(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $printRun = $this->createPrintRun($issueDelivery);

        $response = $this->putForSite("/api/print-runs/{$printRun->id}/cancel", []);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('status', $data['data']);
        $this->assertEquals(PrintRunStatus::CANCELLED->value, $data['data']['status']);
    }

    public function testCancelIsIdempotentOnSecondCallAfterCancel(): void
    {
        // First cancel
        $issueDelivery = $this->createIssueDelivery();
        $printRun = $this->createPrintRun($issueDelivery);
        $this->putForSite("/api/print-runs/{$printRun->id}/cancel", []);

        // Second cancel — should 422 (not pending) not 500
        $response = $this->putForSite("/api/print-runs/{$printRun->id}/cancel", []);

        $this->assertEquals(422, $response->getStatusCode());
    }
}