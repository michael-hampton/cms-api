<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Subscription;

use App\Models\FulfilmentReplacement;
use App\Repositories\Subscriptions\FulfilmentReplacementRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class FulfilmentReplacementRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private FulfilmentReplacementRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new FulfilmentReplacementRepository();
    }

    // ── createReplacement ─────────────────────────────────────────────────────

    public function test_create_replacement_persists_record(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);

        $record = $this->repository->createReplacement(
            subscriptionId: $subscription->id,
            issueId:        $issueDelivery->id,
            reason:         'damaged',
            createdBy:      1,
        );

        $this->assertNotNull($record->id);
        $this->assertEquals($subscription->id,  $record->subscription_id);
        $this->assertEquals($issueDelivery->id, $record->issue_delivery_id);
        $this->assertEquals('damaged',          $record->reason);
        $this->assertEquals(1,                  $record->created_by);
        $this->assertEquals('pending',          $record->status);
    }

    public function test_create_replacement_accepts_explicit_status(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);

        $record = $this->repository->createReplacement(
            subscriptionId: $subscription->id,
            issueId:        $issueDelivery->id,
            reason:         'lost',
            createdBy:      1,
            status:         'queued',
        );

        $this->assertEquals('queued', $record->status);
    }

    // ── updateStatus ──────────────────────────────────────────────────────────

    public function test_update_status_changes_status_on_existing_record(): void
    {
        $replacement = $this->createReplacement(['status' => 'pending']);

        $updated = $this->repository->updateStatus($replacement->id, 'dispatched');

        $this->assertNotNull($updated);
        $this->assertEquals('dispatched', $updated->status);
        $this->assertEquals('dispatched', FulfilmentReplacement::find($replacement->id)->status);
    }

    public function test_update_status_returns_null_for_nonexistent_record(): void
    {
        $result = $this->repository->updateStatus(999999, 'dispatched');

        $this->assertNull($result);
    }

    // ── findBySubscription ────────────────────────────────────────────────────

    public function test_find_by_subscription_returns_all_records_for_subscription(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);

        $this->createReplacement(['subscription_id' => $subscription->id, 'issue_delivery_id' => $issueDelivery->id]);
        $this->createReplacement(['subscription_id' => $subscription->id, 'issue_delivery_id' => $issueDelivery->id]);

        $results = $this->repository->findBySubscription($subscription->id);

        $this->assertCount(2, $results);
        foreach ($results as $record) {
            $this->assertEquals($subscription->id, $record->subscription_id);
        }
    }

    public function test_find_by_subscription_excludes_other_subscriptions(): void
    {
        $subscriptionA = $this->createSubscription();
        $subscriptionB = $this->createSubscription();
        $deliveryA     = $this->createIssueDelivery(['subscription_id' => $subscriptionA->id]);
        $deliveryB     = $this->createIssueDelivery(['subscription_id' => $subscriptionB->id]);

        $this->createReplacement(['subscription_id' => $subscriptionA->id, 'issue_delivery_id' => $deliveryA->id]);
        $this->createReplacement(['subscription_id' => $subscriptionB->id, 'issue_delivery_id' => $deliveryB->id]);

        $results = $this->repository->findBySubscription($subscriptionA->id);

        $this->assertCount(1, $results);
        $this->assertEquals($subscriptionA->id, $results->first()->subscription_id);
    }

    public function test_find_by_subscription_orders_newest_first(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);

        $older = $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'created_at'        => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);
        $newer = $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        $results = $this->repository->findBySubscription($subscription->id);

        $this->assertEquals($newer->id, $results->first()->id);
        $this->assertEquals($older->id, $results->last()->id);
    }

    public function test_find_by_subscription_returns_empty_collection_when_none(): void
    {
        $results = $this->repository->findBySubscription(999999);

        $this->assertCount(0, $results);
    }

    // ── issueExistsForSubscription ────────────────────────────────────────────

    public function test_issue_exists_for_subscription_returns_true_when_matched(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);

        $result = $this->repository->issueExistsForSubscription($issueDelivery->id, $subscription->id);

        $this->assertTrue($result);
    }

    public function test_issue_exists_for_subscription_returns_false_for_wrong_subscription(): void
    {
        $subscriptionA = $this->createSubscription();
        $subscriptionB = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscriptionA->id]);

        $result = $this->repository->issueExistsForSubscription($issueDelivery->id, $subscriptionB->id);

        $this->assertFalse($result);
    }

    public function test_issue_exists_for_subscription_returns_false_for_nonexistent_issue(): void
    {
        $subscription = $this->createSubscription();

        $result = $this->repository->issueExistsForSubscription(999999, $subscription->id);

        $this->assertFalse($result);
    }

    // ── issueExistsForSubscriptionPlan ─────────────────────────────────────────

    public function test_issue_exists_for_subscription_plan_returns_true_when_matched(): void
    {
        $plan = $this->createSubscriptionPlan();
        $issueDelivery = $this->createIssueDelivery(['subscription_plan_id' => $plan->id]);

        $result = $this->repository->issueExistsForSubscriptionPlan($issueDelivery->id, $plan->id);

        $this->assertTrue($result);
    }

    public function test_issue_exists_for_subscription_plan_returns_false_for_wrong_plan(): void
    {
        $planA = $this->createSubscriptionPlan();
        $planB = $this->createSubscriptionPlan();
        $issueDelivery = $this->createIssueDelivery(['subscription_plan_id' => $planA->id]);

        $result = $this->repository->issueExistsForSubscriptionPlan($issueDelivery->id, $planB->id);

        $this->assertFalse($result);
    }

    public function test_issue_exists_for_subscription_plan_returns_false_for_nonexistent_issue(): void
    {
        $plan = $this->createSubscriptionPlan();

        $result = $this->repository->issueExistsForSubscriptionPlan(999999, $plan->id);

        $this->assertFalse($result);
    }

    // ── issueDeliveryWasDispatched ────────────────────────────────────────────

    public function test_issue_delivery_was_dispatched_returns_true_when_dispatched(): void
    {
        $issueDelivery = $this->createIssueDelivery(['status' => 'dispatched']);

        $result = $this->repository->issueDeliveryWasDispatched($issueDelivery->id);

        $this->assertTrue($result);
    }

    public function test_issue_delivery_was_dispatched_returns_false_for_pending_status(): void
    {
        $issueDelivery = $this->createIssueDelivery(['status' => 'pending']);

        $result = $this->repository->issueDeliveryWasDispatched($issueDelivery->id);

        $this->assertFalse($result);
    }

    public function test_issue_delivery_was_dispatched_returns_false_for_nonexistent_issue(): void
    {
        $result = $this->repository->issueDeliveryWasDispatched(999999);

        $this->assertFalse($result);
    }

    // ── issueDeliveryWasDispatchedForSubscriptionPlan ─────────────────────────

    public function test_issue_delivery_was_dispatched_for_subscription_plan_returns_true_when_matched(): void
    {
        $plan = $this->createSubscriptionPlan();
        $issueDelivery = $this->createIssueDelivery([
            'subscription_plan_id' => $plan->id,
            'status' => 'dispatched',
        ]);

        $result = $this->repository->issueDeliveryWasDispatchedForSubscriptionPlan($issueDelivery->id, $plan->id);

        $this->assertTrue($result);
    }

    public function test_issue_delivery_was_dispatched_for_subscription_plan_returns_false_for_pending_status(): void
    {
        $plan = $this->createSubscriptionPlan();
        $issueDelivery = $this->createIssueDelivery([
            'subscription_plan_id' => $plan->id,
            'status' => 'pending',
        ]);

        $result = $this->repository->issueDeliveryWasDispatchedForSubscriptionPlan($issueDelivery->id, $plan->id);

        $this->assertFalse($result);
    }

    public function test_issue_delivery_was_dispatched_for_subscription_plan_returns_false_for_wrong_plan(): void
    {
        $planA = $this->createSubscriptionPlan();
        $planB = $this->createSubscriptionPlan();
        $issueDelivery = $this->createIssueDelivery([
            'subscription_plan_id' => $planA->id,
            'status' => 'dispatched',
        ]);

        $result = $this->repository->issueDeliveryWasDispatchedForSubscriptionPlan($issueDelivery->id, $planB->id);

        $this->assertFalse($result);
    }

    // ── hasOpenReplacement ────────────────────────────────────────────────────

    public function test_has_open_replacement_returns_true_for_pending_status(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);
        $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'status'            => 'pending',
        ]);

        $result = $this->repository->hasOpenReplacement($subscription->id, $issueDelivery->id);

        $this->assertTrue($result);
    }

    public function test_has_open_replacement_returns_true_for_queued_status(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);
        $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'status'            => 'queued',
        ]);

        $result = $this->repository->hasOpenReplacement($subscription->id, $issueDelivery->id);

        $this->assertTrue($result);
    }

    public function test_has_open_replacement_returns_true_for_dispatched_status(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);
        $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'status'            => 'dispatched',
        ]);

        $result = $this->repository->hasOpenReplacement($subscription->id, $issueDelivery->id);

        $this->assertTrue($result);
    }

    public function test_has_open_replacement_returns_false_for_failed_status(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);
        $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'status'            => 'failed',
        ]);

        $result = $this->repository->hasOpenReplacement($subscription->id, $issueDelivery->id);

        $this->assertFalse($result);
    }

    public function test_has_open_replacement_returns_false_for_cancelled_status(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);
        $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'status'            => 'cancelled',
        ]);

        $result = $this->repository->hasOpenReplacement($subscription->id, $issueDelivery->id);

        $this->assertFalse($result);
    }

    public function test_has_open_replacement_returns_false_for_rejected_status(): void
    {
        $subscription  = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscription->id]);
        $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'status'            => 'rejected',
        ]);

        $result = $this->repository->hasOpenReplacement($subscription->id, $issueDelivery->id);

        $this->assertFalse($result);
    }

    public function test_has_open_replacement_returns_false_when_no_records_exist(): void
    {
        $result = $this->repository->hasOpenReplacement(999999, 999999);

        $this->assertFalse($result);
    }

    public function test_has_open_replacement_scoped_to_correct_subscription(): void
    {
        $subscriptionA = $this->createSubscription();
        $subscriptionB = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscriptionA->id]);

        $this->createReplacement([
            'subscription_id'   => $subscriptionA->id,
            'issue_delivery_id' => $issueDelivery->id,
            'status'            => 'pending',
        ]);

        // Subscription B has no open replacement for that issue delivery
        $result = $this->repository->hasOpenReplacement($subscriptionB->id, $issueDelivery->id);

        $this->assertFalse($result);
    }

    // ── findOpenReplacementsForIssues ─────────────────────────────────────────

    public function test_find_open_replacements_for_issues_returns_empty_collection_for_empty_input(): void
    {
        $subscription = $this->createSubscription();

        $results = $this->repository->findOpenReplacementsForIssues($subscription->id, []);

        $this->assertCount(0, $results);
    }

    public function test_find_open_replacements_for_issues_returns_open_records_only(): void
    {
        $subscription  = $this->createSubscription();
        $issueA        = $this->createIssueDelivery(['subscription_id' => $subscription->id]);
        $issueB        = $this->createIssueDelivery(['subscription_id' => $subscription->id]);

        $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueA->id,
            'status'            => 'pending',
        ]);
        $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueB->id,
            'status'            => 'failed',
        ]);

        $results = $this->repository->findOpenReplacementsForIssues(
            $subscription->id,
            [$issueA->id, $issueB->id],
        );

        $this->assertCount(1, $results);
        $this->assertEquals($issueA->id, $results->first()->issue_delivery_id);
    }

    public function test_find_open_replacements_for_issues_includes_queued_and_dispatched(): void
    {
        $subscription = $this->createSubscription();
        $issueA       = $this->createIssueDelivery(['subscription_id' => $subscription->id]);
        $issueB       = $this->createIssueDelivery(['subscription_id' => $subscription->id]);
        $issueC       = $this->createIssueDelivery(['subscription_id' => $subscription->id]);

        foreach (['pending', 'queued', 'dispatched'] as $i => $status) {
            $issue = [$issueA, $issueB, $issueC][$i];
            $this->createReplacement([
                'subscription_id'   => $subscription->id,
                'issue_delivery_id' => $issue->id,
                'status'            => $status,
            ]);
        }

        $results = $this->repository->findOpenReplacementsForIssues(
            $subscription->id,
            [$issueA->id, $issueB->id, $issueC->id],
        );

        $this->assertCount(3, $results);
    }

    public function test_find_open_replacements_for_issues_excludes_other_subscriptions(): void
    {
        $subscriptionA = $this->createSubscription();
        $subscriptionB = $this->createSubscription();
        $issueDelivery = $this->createIssueDelivery(['subscription_id' => $subscriptionA->id]);

        $this->createReplacement([
            'subscription_id'   => $subscriptionA->id,
            'issue_delivery_id' => $issueDelivery->id,
            'status'            => 'pending',
        ]);

        $results = $this->repository->findOpenReplacementsForIssues(
            $subscriptionB->id,
            [$issueDelivery->id],
        );

        $this->assertCount(0, $results);
    }

    public function test_find_open_replacements_for_issues_excludes_issue_ids_not_in_list(): void
    {
        $subscription  = $this->createSubscription();
        $issueA        = $this->createIssueDelivery(['subscription_id' => $subscription->id]);
        $issueB        = $this->createIssueDelivery(['subscription_id' => $subscription->id]);

        $this->createReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueA->id,
            'status'            => 'pending',
        ]);

        // Only pass $issueB->id — $issueA should not appear
        $results = $this->repository->findOpenReplacementsForIssues(
            $subscription->id,
            [$issueB->id],
        );

        $this->assertCount(0, $results);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function createReplacement(array $overrides = []): FulfilmentReplacement
    {
        $subscription  = isset($overrides['subscription_id'])
            ? (object)['id' => $overrides['subscription_id']]
            : $this->createSubscription();

        $issueDelivery = isset($overrides['issue_delivery_id'])
            ? (object)['id' => $overrides['issue_delivery_id']]
            : $this->createIssueDelivery(['subscription_id' => $subscription->id]);

        return FulfilmentReplacement::create(array_merge([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'reason'            => 'damaged',
            'created_by'        => 1,
            'status'            => 'pending',
        ], $overrides));
    }
}
