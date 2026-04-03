<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Workflow\WorkflowRunStatus;
use App\Models\Model;
use App\Models\WorkflowRun;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class WorkflowRunControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // Helpers
    // =========================================================================

    public function testIndexReturnsPaginatedRuns(): void
    {
        $this->createWorkflowRun();
        $this->createWorkflowRun();

        $response = $this->getForSite('/api/workflow-runs');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertArrayHasKey('total', $data['pagination']);
        $this->assertGreaterThanOrEqual(2, $data['pagination']['total']);
    }

    // =========================================================================
    // GET /api/workflow-runs
    // =========================================================================

    private function createWorkflowRun(array $overrides = []): Model
    {
        return WorkflowRun::create(array_merge([
            'workflow_type' => 'App\\Workflows\\PrintRunWorkflow',
            'status' => WorkflowRunStatus::COMPLETE->value,
            'input' => [
                'triggered_by' => 'IssueDeliveryDispatchedListener',
                'issue_delivery_id' => 408,
            ],
            'summary' => [
                'phase_2' => [
                    'status' => 'succeeded',
                    'summary' => ['batch_count' => 1],
                    'recorded_at' => '2026-03-31 11:39:54',
                ],
                'phase_3' => [
                    'status' => 'succeeded',
                    'summary' => ['batch_count' => 19],
                    'recorded_at' => '2026-03-31 11:39:54',
                ],
            ],
            'error' => null,
            'started_at' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
            'completed_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function testIndexResponseShapeIncludesExpectedFields(): void
    {
        $run = $this->createWorkflowRun();

        $response = $this->getForSite('/api/workflow-runs');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $item = collect($data['items'])->firstWhere('id', $run->id);
        $this->assertNotNull($item, 'Created run must appear in index response');

        foreach (['id', 'workflow_type', 'status', 'input', 'summary', 'error', 'started_at', 'completed_at'] as $field) {
            $this->assertArrayHasKey($field, $item, "Missing field: {$field}");
        }
    }

    public function testIndexFiltersOnStatus(): void
    {
        $complete = $this->createWorkflowRun(['status' => WorkflowRunStatus::COMPLETE->value]);
        $this->createWorkflowRun(['status' => WorkflowRunStatus::FAILED->value]);

        $response = $this->getForSite('/api/workflow-runs?status=complete');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        foreach ($data['items'] as $item) {
            $this->assertEquals(WorkflowRunStatus::COMPLETE->value, $item['status']);
        }

        $ids = array_column($data['items'], 'id');
        $this->assertContains($complete->id, $ids);
    }

    public function testIndexFiltersOnFailedStatus(): void
    {
        $failed = $this->createWorkflowRun([
            'status' => WorkflowRunStatus::FAILED->value,
            'error' => 'Connection timeout',
        ]);
        $this->createWorkflowRun(['status' => WorkflowRunStatus::COMPLETE->value]);

        $response = $this->getForSite('/api/workflow-runs?status=failed');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['items'], 'id');
        $this->assertContains($failed->id, $ids);

        foreach ($data['items'] as $item) {
            $this->assertEquals(WorkflowRunStatus::FAILED->value, $item['status']);
        }
    }

    public function testIndexFiltersOnNoDataStatus(): void
    {
        $noData = $this->createWorkflowRun([
            'status' => WorkflowRunStatus::NO_DATA->value,
            'summary' => [],
        ]);

        $response = $this->getForSite('/api/workflow-runs?status=no_data');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['items'], 'id');
        $this->assertContains($noData->id, $ids);
    }

    public function testIndexFiltersOnWorkflowType(): void
    {
        $print = $this->createWorkflowRun(['workflow_type' => 'App\\Workflows\\PrintRunWorkflow']);
        $this->createWorkflowRun(['workflow_type' => 'App\\Workflows\\EmailDispatchWorkflow']);

        $response = $this->getForSite('/api/workflow-runs?workflow_type=PrintRunWorkflow');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['items'], 'id');

        $this->assertContains($print->id, $ids);

        // The email workflow must not appear when filtering for PrintRunWorkflow
        foreach ($data['items'] as $item) {
            $this->assertStringContainsString('PrintRunWorkflow', $item['workflow_type']);
        }
    }

    public function testIndexSearchMatchesOnWorkflowType(): void
    {
        $target = $this->createWorkflowRun(['workflow_type' => 'App\\Workflows\\PrintRunWorkflow']);
        $this->createWorkflowRun(['workflow_type' => 'App\\Workflows\\EmailDispatchWorkflow']);

        $response = $this->getForSite('/api/workflow-runs?search=PrintRun');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['items'], 'id');
        $this->assertContains($target->id, $ids);
    }

    public function testIndexIgnoresUnrecognisedStatusFilter(): void
    {
        $this->createWorkflowRun();

        // An unrecognised status value must not crash — returns all results
        $response = $this->getForSite('/api/workflow-runs?status=not-a-valid-status');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexSummaryFieldIsFormattedCorrectly(): void
    {
        $run = $this->createWorkflowRun();

        $response = $this->getForSite('/api/workflow-runs');
        $data = json_decode($response->getContent(), true);

        $item = collect($data['items'])->firstWhere('id', $run->id);
        $this->assertNotNull($item);

        $summary = $item['summary'];
        $this->assertArrayHasKey('phase_2', $summary);
        $this->assertArrayHasKey('phase_3', $summary);

        // Each phase must have the normalised shape
        foreach (['phase_2', 'phase_3'] as $phase) {
            $this->assertArrayHasKey('status', $summary[$phase]);
            $this->assertArrayHasKey('summary', $summary[$phase]);
            $this->assertArrayHasKey('recorded_at', $summary[$phase]);
            $this->assertEquals('succeeded', $summary[$phase]['status']);
        }
    }

    public function testIndexInputFieldIsIncluded(): void
    {
        $run = $this->createWorkflowRun([
            'input' => [
                'triggered_by' => 'IssueDeliveryDispatchedListener',
                'issue_delivery_id' => 999,
            ],
        ]);

        $response = $this->getForSite('/api/workflow-runs');
        $data = json_decode($response->getContent(), true);

        $item = collect($data['items'])->firstWhere('id', $run->id);
        $this->assertNotNull($item);

        $this->assertArrayHasKey('input', $item);
        $this->assertEquals('IssueDeliveryDispatchedListener', $item['input']['triggered_by']);
        $this->assertEquals(999, $item['input']['issue_delivery_id']);
    }

    public function testIndexRunWithNullSummaryReturnsSummaryAsNull(): void
    {
        $run = $this->createWorkflowRun([
            'status' => WorkflowRunStatus::RUNNING->value,
            'summary' => null,
        ]);

        $response = $this->getForSite('/api/workflow-runs');
        $data = json_decode($response->getContent(), true);

        $item = collect($data['items'])->firstWhere('id', $run->id);
        $this->assertNotNull($item);
        $this->assertNull($item['summary']);
    }

    // =========================================================================
    // GET /api/workflow-runs/{id}
    // =========================================================================

    public function testShowReturnsSingleRunDetail(): void
    {
        $run = $this->createWorkflowRun();

        $response = $this->getForSite("/api/workflow-runs/{$run->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($run->id, $data['data']['id']);
    }

    public function testShowResponseShapeIncludesAllFields(): void
    {
        $run = $this->createWorkflowRun();

        $response = $this->getForSite("/api/workflow-runs/{$run->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());

        foreach (['id', 'workflow_type', 'status', 'input', 'summary', 'error', 'started_at', 'completed_at', 'created_at', 'updated_at'] as $field) {
            $this->assertArrayHasKey($field, $data['data'], "Missing field: {$field}");
        }
    }

    public function testShowReturns404WhenRunNotFound(): void
    {
        $response = $this->getForSite('/api/workflow-runs/99999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testShowReturnsSummaryWithCorrectPhaseShape(): void
    {
        $run = $this->createWorkflowRun([
            'summary' => [
                'phase_2' => [
                    'status' => 'succeeded',
                    'summary' => ['batch_count' => 5],
                    'recorded_at' => '2026-03-31 11:39:54',
                ],
            ],
        ]);

        $response = $this->getForSite("/api/workflow-runs/{$run->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());

        $summary = $data['data']['summary'];
        $this->assertArrayHasKey('phase_2', $summary);
        $this->assertEquals('succeeded', $summary['phase_2']['status']);
        $this->assertEquals(5, $summary['phase_2']['summary']['batch_count']);
        $this->assertEquals('2026-03-31 11:39:54', $summary['phase_2']['recorded_at']);
    }

    public function testShowReturnsErrorFieldForFailedRun(): void
    {
        $run = $this->createWorkflowRun([
            'status' => WorkflowRunStatus::FAILED->value,
            'error' => 'Connection timed out after 30s',
        ]);

        $response = $this->getForSite("/api/workflow-runs/{$run->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Connection timed out after 30s', $data['data']['error']);
    }

    public function testShowReturnsNullErrorForSuccessfulRun(): void
    {
        $run = $this->createWorkflowRun([
            'status' => WorkflowRunStatus::COMPLETE->value,
            'error' => null,
        ]);

        $response = $this->getForSite("/api/workflow-runs/{$run->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertNull($data['data']['error']);
    }

    public function testShowReturnsCompletedAtForFinishedRun(): void
    {
        $completedAt = '2026-03-31 12:00:00';
        $run = $this->createWorkflowRun(['completed_at' => $completedAt]);

        $response = $this->getForSite("/api/workflow-runs/{$run->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($completedAt, $data['data']['completed_at']);
    }

    public function testShowReturnsNullCompletedAtForRunningRun(): void
    {
        $run = $this->createWorkflowRun([
            'status' => WorkflowRunStatus::RUNNING->value,
            'completed_at' => null,
            'summary' => null,
        ]);

        $response = $this->getForSite("/api/workflow-runs/{$run->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertNull($data['data']['completed_at']);
    }
}