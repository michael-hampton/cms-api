<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\LabelExportFormat;
use App\Enums\Subscriptions\LabelRunStatus;
use App\Models\LabelRun;
use App\Repositories\Subscriptions\LabelRunRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class LabelRunRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private LabelRunRepository $repository;

    public function test_create_for_subscription_issue_fulfilments_persists_record(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);

        // Act
        $labelRun = $this->repository->createForSubscriptionIssueFulfilment(
            subscriptionIssueFulfilmentId: $issueDelivered->id,
            subscriptionId: $subscription->id,
            format: LabelExportFormat::Pdf,
        );

        // Assert
        $this->assertNotNull($labelRun->id);
        $this->assertEquals($issueDelivered->id, $labelRun->subscription_issue_fulfilment_id);
        $this->assertEquals($subscription->id, $labelRun->subscription_id);
    }

    // -------------------------------------------------------------------------
    // createForSubscriptionIssueFulfilment
    // -------------------------------------------------------------------------

    public function test_create_for_subscription_issue_fulfilments_sets_pending_status(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);

        // Act
        $labelRun = $this->repository->createForSubscriptionIssueFulfilment(
            subscriptionIssueFulfilmentId: $issueDelivered->id,
            subscriptionId: $subscription->id,
            format: LabelExportFormat::Pdf,
        );

        // Assert
        $this->assertEquals(LabelRunStatus::Pending->value, $labelRun->status);
    }

    public function test_create_for_subscription_issue_fulfilments_sets_zero_attempt_count(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);

        // Act
        $labelRun = $this->repository->createForSubscriptionIssueFulfilment(
            subscriptionIssueFulfilmentId: $issueDelivered->id,
            subscriptionId: $subscription->id,
            format: LabelExportFormat::Pdf,
        );

        // Assert
        $this->assertEquals(0, $labelRun->attempt_count);
    }

    public function test_create_for_subscription_issue_fulfilments_stores_format(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);

        // Act
        $labelRun = $this->repository->createForSubscriptionIssueFulfilment(
            subscriptionIssueFulfilmentId: $issueDelivered->id,
            subscriptionId: $subscription->id,
            format: LabelExportFormat::Csv,
        );

        // Assert
        $this->assertEquals(LabelExportFormat::Csv->value, $labelRun->format);
    }

    public function test_create_for_subscription_issue_fulfilments_stores_optional_print_batch_id(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();

        // Act
        $labelRun = $this->repository->createForSubscriptionIssueFulfilment(
            subscriptionIssueFulfilmentId: $issueDelivered->id,
            subscriptionId: $subscription->id,
            format: LabelExportFormat::Pdf,
            printBatchId: $printBatch->id,
        );

        // Assert
        $this->assertEquals($printBatch->id, $labelRun->print_batch_id);
    }

    public function test_create_for_subscription_issue_fulfilments_allows_null_print_batch_id(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);

        // Act
        $labelRun = $this->repository->createForSubscriptionIssueFulfilment(
            subscriptionIssueFulfilmentId: $issueDelivered->id,
            subscriptionId: $subscription->id,
            format: LabelExportFormat::Pdf,
            printBatchId: null,
        );

        // Assert
        $this->assertNull($labelRun->print_batch_id);
    }

    public function test_find_returns_existing_label_run(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);

        // Arrange
        $created = $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf);

        // Act
        $found = $this->repository->find($created->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_find_returns_null_for_nonexistent_id(): void
    {
        // Act
        $result = $this->repository->find(99999);

        // Assert
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // find
    // -------------------------------------------------------------------------

    public function test_find_by_batch_returns_all_runs_for_batch(): void
    {
        $subscription = $this->createSubscription();
        $subscription2 = $this->createSubscription();
        $subscription3 = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $issueDelivered2 = $this->createIssueDelivered($subscription);
        $issueDelivered3 = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();
        $printBatch3 = $this->createPrintBatch();

        // Arrange
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf, $printBatch->id);
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered2->id, $subscription2->id, LabelExportFormat::Pdf, $printBatch3->id);
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered3->id, $subscription3->id, LabelExportFormat::Pdf, $printBatch3->id); // different batch

        // Act
        $results = $this->repository->findByBatch($printBatch3->id);

        // Assert
        $this->assertCount(2, $results);
        $results->each(fn($r) => $this->assertEquals($printBatch3->id, $r->print_batch_id));
    }

    public function test_find_by_batch_returns_empty_collection_when_none_exist(): void
    {
        // Act
        $results = $this->repository->findByBatch(999);

        // Assert
        $this->assertCount(0, $results);
    }

    // -------------------------------------------------------------------------
    // findByBatch
    // -------------------------------------------------------------------------

    public function test_find_pending_by_batch_returns_only_pending(): void
    {
        $subscription = $this->createSubscription();
        $subscription2 = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $issueDelivered2 = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();

        // Arrange
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf, $printBatch->id);
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered2->id, $subscription2->id, LabelExportFormat::Pdf, $printBatch->id);

        // Manually set one to complete
        LabelRun::where('subscription_issue_fulfilment_id', $issueDelivered2->id)->update(['status' => LabelRunStatus::Complete->value]);

        // Act
        $results = $this->repository->findPendingByBatch($printBatch->id);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals(LabelRunStatus::Pending->value, $results->first()->status);
    }

    public function test_find_pending_by_batch_excludes_failed_runs(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();

        // Arrange
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf, $printBatch->id);
        LabelRun::where('subscription_issue_fulfilment_id', 1)->update(['status' => LabelRunStatus::Failed->value]);

        // Act
        $results = $this->repository->findPendingByBatch($printBatch->id);

        // Assert
        $this->assertCount(0, $results);
    }

    // -------------------------------------------------------------------------
    // findPendingByBatch
    // -------------------------------------------------------------------------

    public function test_find_retryable_by_batch_returns_failed_under_max_attempts(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();
        // Arrange
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf, $printBatch->id);
        LabelRun::where('subscription_issue_fulfilment_id', 1)->update([
            'status' => LabelRunStatus::Failed->value,
            'attempt_count' => 2,
        ]);

        // Act
        $results = $this->repository->findRetryableByBatch($printBatch->id, maxAttempts: 3);

        // Assert
        $this->assertCount(1, $results);
    }

    public function test_find_retryable_by_batch_excludes_runs_at_max_attempts(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();

        // Arrange
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf, $printBatch->id);
        LabelRun::where('subscription_issue_fulfilment_id', 1)->update([
            'status' => LabelRunStatus::Failed->value,
            'attempt_count' => 3,
        ]);

        // Act
        $results = $this->repository->findRetryableByBatch($printBatch->id, maxAttempts: 3);

        // Assert
        $this->assertCount(0, $results);
    }

    // -------------------------------------------------------------------------
    // findRetryableByBatch
    // -------------------------------------------------------------------------

    public function test_find_retryable_by_batch_excludes_pending_runs(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();

        // Arrange — pending run has attempt_count 0, but is not failed
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf, $printBatch->id);

        // Act
        $results = $this->repository->findRetryableByBatch($printBatch->id);

        // Assert
        $this->assertCount(0, $results);
    }

    public function test_exists_for_subscription_issue_fulfilments_and_batch_returns_true_when_found(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();

        // Arrange
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf, $printBatch->id);

        // Act
        $exists = $this->repository->existsForSubscriptionIssueFulfilmentAndBatch($issueDelivered->id, $printBatch->id);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_exists_for_subscription_issue_fulfilments_and_batch_returns_false_when_not_found(): void
    {

        // Act
        $exists = $this->repository->existsForSubscriptionIssueFulfilmentAndBatch(99, 99);

        // Assert
        $this->assertFalse($exists);
    }

    // -------------------------------------------------------------------------
    // existsForSubscriptionIssueFulfilmentAndBatch
    // -------------------------------------------------------------------------

    public function test_exists_for_subscription_issue_fulfilments_and_batch_requires_both_to_match(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();

        // Arrange
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf, $printBatch->id);

        // Act — same subscription_issue_fulfilment_id but different batch
        $exists = $this->repository->existsForSubscriptionIssueFulfilmentAndBatch($issueDelivered->id, 99);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_count_by_status_for_batch_returns_correct_counts(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();

        $subscription2 = $this->createSubscription();
        $issueDelivered2 = $this->createIssueDelivered($subscription);

        $subscription3 = $this->createSubscription();
        $issueDelivered3 = $this->createIssueDelivered($subscription);

        // Arrange
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf, $printBatch->id);
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered2->id, $subscription2->id, LabelExportFormat::Pdf, $printBatch->id);
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered3->id, $subscription3->id, LabelExportFormat::Pdf, $printBatch->id);

        LabelRun::where('subscription_issue_fulfilment_id', $issueDelivered2->id)->update(['status' => LabelRunStatus::Complete->value]);
        LabelRun::where('subscription_issue_fulfilment_id', $issueDelivered3->id)->update(['status' => LabelRunStatus::Failed->value]);

        // Act
        $counts = $this->repository->countByStatusForBatch($printBatch->id);

        // Assert
        $this->assertEquals(1, $counts['pending']);
        $this->assertEquals(1, $counts['complete']);
        $this->assertEquals(1, $counts['failed']);
        $this->assertEquals(0, $counts['generating']);
    }

    public function test_count_by_status_for_batch_returns_zeros_for_empty_batch(): void
    {
        // Act
        $counts = $this->repository->countByStatusForBatch(999);

        // Assert
        $this->assertEquals(0, $counts['pending']);
        $this->assertEquals(0, $counts['generating']);
        $this->assertEquals(0, $counts['complete']);
        $this->assertEquals(0, $counts['failed']);
    }

    // -------------------------------------------------------------------------
    // countByStatusForBatch
    // -------------------------------------------------------------------------

    public function test_count_by_status_for_batch_excludes_other_batches(): void
    {
        $subscription = $this->createSubscription();
        $issueDelivered = $this->createIssueDelivered($subscription);
        $printBatch = $this->createPrintBatch();

        $subscription2 = $this->createSubscription();
        $issueDelivered2 = $this->createIssueDelivered($subscription);
        $printBatch2 = $this->createPrintBatch();

        // Arrange
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered->id, $subscription->id, LabelExportFormat::Pdf, $printBatch->id);
        $this->repository->createForSubscriptionIssueFulfilment($issueDelivered2->id, $subscription2->id, LabelExportFormat::Pdf, $printBatch2->id); // different batch

        // Act
        $counts = $this->repository->countByStatusForBatch($printBatch->id);

        // Assert
        $this->assertEquals(1, $counts['pending']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new LabelRunRepository();
    }
}