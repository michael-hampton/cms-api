<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\LabelExportFormat;
use App\Models\LabelRun;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class LabelRunReportControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createLabelRun(array $overrides = []): LabelRun
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);

        return LabelRun::create(array_merge([
            'subscription_issue_fulfilment_id' => $issueDelivered->id,
            'subscription_id' => $subscription->id,
            'status' => \App\Enums\Subscriptions\LabelRunStatus::Pending->value,
            'format' => LabelExportFormat::Pdf->value,
            'attempt_count' => 0,
        ], $overrides));
    }

    // =========================================================================
    // GET /api/label-runs
    // =========================================================================

    public function testIndexReturnsPaginatedLabelRuns(): void
    {
        $this->createLabelRun();
        $this->createLabelRun();

        $response = $this->getForSite('/api/label-runs');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertGreaterThanOrEqual(2, $data['pagination']['total']);
    }

    public function testIndexFiltersByCreatedAtRange(): void
    {
        $inRange = $this->createLabelRun();
        $outOfRange = $this->createLabelRun();

        LabelRun::where('id', $inRange->id)->update(['created_at' => '2026-03-15 10:00:00']);
        LabelRun::where('id', $outOfRange->id)->update(['created_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite('/api/label-runs?from=2026-03-01&to=2026-03-31');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['data'], 'id');
        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($outOfRange->id, $ids);
    }

    public function testIndexFiltersByUpdatedAtRange(): void
    {
        $inRange = $this->createLabelRun();
        $outOfRange = $this->createLabelRun();

        LabelRun::where('id', $inRange->id)->update(['updated_at' => '2026-03-15 10:00:00']);
        LabelRun::where('id', $outOfRange->id)->update(['updated_at' => '2026-01-01 10:00:00']);

        $response = $this->getForSite('/api/label-runs?updated_from=2026-03-01&updated_to=2026-03-31');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['data'], 'id');
        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($outOfRange->id, $ids);
    }
}
