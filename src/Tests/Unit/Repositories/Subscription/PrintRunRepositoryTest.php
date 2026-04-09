<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\PrintRunStatus;
use App\Models\Model;
use App\Models\PrintRun;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class PrintRunRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PrintRunRepository $repository;

    public function test_pending_for_issue_delivery_returns_pending_runs(): void
    {
        $issueDelivery = $this->createIssueDelivery();

        // Arrange
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::PENDING->value]);
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::PENDING->value]);
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::CANCELLED->value]);

        // Act
        $results = $this->repository->pendingForIssueDelivery($issueDelivery->id);

        // Assert
        $this->assertCount(2, $results);
        foreach ($results as $run) {
            $this->assertEquals(PrintRunStatus::PENDING->value, $run->status);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createPrintRun(array $overrides = []): Model
    {
        $issueDelivery = $this->createIssueDelivery();

        return PrintRun::create(array_merge([
            'issue_delivery_id' => $issueDelivery->id,
            'status' => PrintRunStatus::PENDING->value,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // pendingForIssueDelivery
    // -------------------------------------------------------------------------

    public function test_pending_for_issue_delivery_excludes_other_deliveries(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $issueDelivery2 = $this->createIssueDelivery();

        // Arrange
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id]);
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery2->id]);

        // Act
        $results = $this->repository->pendingForIssueDelivery($issueDelivery->id);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals(1, $results->first()->issue_delivery_id);
    }

    public function test_pending_for_issue_delivery_returns_empty_when_none(): void
    {
        // Act
        $results = $this->repository->pendingForIssueDelivery(999);

        // Assert
        $this->assertCount(0, $results);
    }

    public function test_search_returns_all_when_no_filters(): void
    {
        // Arrange
        $this->createPrintRun();
        $this->createPrintRun();

        // Act
        $result = $this->repository->search([], 25);


        // Assert
        $this->assertEquals(2, $result['data']->count());
    }

    // -------------------------------------------------------------------------
    // search
    // -------------------------------------------------------------------------

    public function test_search_filters_by_status(): void
    {
        // Arrange
        $this->createPrintRun(['status' => PrintRunStatus::PENDING->value]);
        $this->createPrintRun(['status' => PrintRunStatus::CANCELLED->value]);

        // Act
        $result = $this->repository->search(['status' => PrintRunStatus::PENDING->value]);

        // Assert
        $this->assertEquals(1, $result['data']->count());
        $this->assertEquals(PrintRunStatus::PENDING->value, $result['data']->all()[0]->status);
    }

    public function test_search_filters_by_issue_delivery_id(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $issueDelivery2 = $this->createIssueDelivery();

        // Arrange
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id]);
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery2->id]);

        // Act
        $result = $this->repository->search(['issue_delivery_id' => $issueDelivery->id]);

        // Assert
        $this->assertEquals(1, $result['data']->count());
        $this->assertEquals($issueDelivery->id, $result['data']->all()[0]->issue_delivery_id);
    }

    public function test_search_respects_per_page(): void
    {
        // Arrange
        $this->createPrintRun();
        $this->createPrintRun();
        $this->createPrintRun();

        // Act
        $result = $this->repository->search([], 2);

        // Assert
        $this->assertEquals(2, $result['data']->count());
        $this->assertCount(2, $result['data']->all());
    }

    public function test_list_for_issue_delivery_returns_all_runs_for_delivery(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $issueDelivery2 = $this->createIssueDelivery();
        // Arrange
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::PENDING->value]);
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::CANCELLED->value]);
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery2->id]);

        // Act
        $results = $this->repository->listForIssueDelivery($issueDelivery->id);

        // Assert
        $this->assertCount(2, $results);
    }

    // -------------------------------------------------------------------------
    // listForIssueDelivery
    // -------------------------------------------------------------------------

    public function test_list_for_issue_delivery_filters_by_status(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        // Arrange
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::PENDING->value]);
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::CANCELLED->value]);

        // Act
        $results = $this->repository->listForIssueDelivery($issueDelivery->id, ['status' => PrintRunStatus::CANCELLED->value]);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals(PrintRunStatus::CANCELLED->value, $results->first()->status);
    }

    public function test_cancel_all_pending_updates_pending_runs_to_cancelled(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        // Arrange
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::PENDING->value]);
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::PENDING->value]);

        // Act
        $count = $this->repository->cancelAllPendingForIssueDelivery($issueDelivery->id);

        // Assert
        $this->assertEquals(2, $count);
        $this->assertEquals(
            0,
            PrintRun::where('issue_delivery_id', $issueDelivery->id)
                ->where('status', PrintRunStatus::PENDING->value)
                ->count()
        );
    }

    // -------------------------------------------------------------------------
    // cancelAllPendingForIssueDelivery
    // -------------------------------------------------------------------------

    public function test_cancel_all_pending_does_not_affect_non_pending_runs(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        // Arrange
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::FAILED->value]);

        // Act
        $count = $this->repository->cancelAllPendingForIssueDelivery($issueDelivery->id);

        // Assert
        $this->assertEquals(0, $count);
        $this->assertEquals(
            PrintRunStatus::FAILED->value,
            PrintRun::where('issue_delivery_id', $issueDelivery->id)->first()->status
        );
    }

    public function test_cancel_all_pending_excludes_other_issue_deliveries(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $issueDelivery2 = $this->createIssueDelivery();
        // Arrange
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::PENDING->value]);
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery2->id, 'status' => PrintRunStatus::PENDING->value]);

        // Act
        $this->repository->cancelAllPendingForIssueDelivery($issueDelivery->id);

        // Assert — delivery 2's pending run is untouched
        $this->assertEquals(
            PrintRunStatus::PENDING->value,
            PrintRun::where('issue_delivery_id', $issueDelivery2->id)->first()->status
        );
    }

    public function test_find_active_for_issue_delivery_returns_latest_non_terminal_run(): void
    {
        $issueDelivery = $this->createIssueDelivery();

        // Arrange
        $older = $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::PENDING->value]);
        sleep(1); // ensure created_at differs
        $newer = $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::PENDING->value]);

        // Act
        $result = $this->repository->findActiveForIssueDelivery(1);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($newer->id, $result->id);
    }

    // -------------------------------------------------------------------------
    // findActiveForIssueDelivery
    // -------------------------------------------------------------------------

    public function test_find_active_for_issue_delivery_excludes_cancelled_runs(): void
    {
        $issueDelivery = $this->createIssueDelivery();

        // Arrange
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::CANCELLED->value]);

        // Act
        $result = $this->repository->findActiveForIssueDelivery($issueDelivery->id);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_active_for_issue_delivery_excludes_failed_runs(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        // Arrange
        $this->createPrintRun(['issue_delivery_id' => $issueDelivery->id, 'status' => PrintRunStatus::FAILED->value]);

        // Act
        $result = $this->repository->findActiveForIssueDelivery($issueDelivery->id);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_active_for_issue_delivery_returns_null_when_none(): void
    {
        // Act
        $result = $this->repository->findActiveForIssueDelivery(999);

        // Assert
        $this->assertNull($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PrintRunRepository();
    }
}