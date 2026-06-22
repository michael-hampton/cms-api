<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\SubscriptionIssueFulfilmentStatus;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\SubscriptionIssueFulfilment;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionIssueFulfilmentRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private SubscriptionIssueFulfilmentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionIssueFulfilmentRepository();
    }

    public function test_create_for_subscription_is_idempotent_and_keeps_schedule_fields(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $scheduledFor = new \DateTime('+7 days');
        $deferredUntil = new \DateTime('+14 days');

        $first = $this->repository->createForSubscription(
            $subscription->id,
            $issue->id,
            $scheduledFor,
            $deferredUntil
        );
        $second = $this->repository->createForSubscription(
            $subscription->id,
            $issue->id,
            new \DateTime('+20 days')
        );

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals($scheduledFor->format('Y-m-d'), $first->scheduled_for->format('Y-m-d'));
        $this->assertEquals($deferredUntil->format('Y-m-d'), $first->deferred_until->format('Y-m-d'));
    }

    public function test_existing_legacy_row_gets_missing_schedule_date_repaired(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $row = SubscriptionIssueFulfilment::create([
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issue->id,
            'status' => SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
            'attempts' => 0,
            'scheduled_for' => null,
        ]);
        $scheduledFor = new \DateTime('+7 days');

        $returned = $this->repository->createForSubscription(
            $subscription->id,
            $issue->id,
            $scheduledFor
        );

        $this->assertEquals($row->id, $returned->id);
        $this->assertEquals($scheduledFor->format('Y-m-d'), $returned->scheduled_for->format('Y-m-d'));
    }

    public function test_claim_for_dispatch_only_claims_due_scheduled_undispatched_rows_once(): void
    {
        [$subscription] = $this->createSubscriptionAndIssue();
        $claimableIssue = $this->createIssue($subscription->plan_id, 2, '-1 minute');
        $claimable = $this->repository->createFromSchedule($subscription->id, $claimableIssue);
        $otherIssue = $this->createIssue($subscription->plan_id, 3, '-2 minutes');
        $alreadyDispatched = $this->repository->createFromSchedule($subscription->id, $otherIssue);
        $alreadyDispatched->markAsDispatched(new \DateTime('-1 minute'));

        $claimed = $this->repository->claimForDispatch(
            [$claimable->id, $alreadyDispatched->id],
            new \DateTime()
        );
        $claimedAgain = $this->repository->claimForDispatch([$claimable->id], new \DateTime());

        $this->assertSame([$claimable->id], $claimed);
        $this->assertSame([], $claimedAgain);
        $this->assertNotNull(SubscriptionIssueFulfilment::find($claimable->id)->dispatched_at);
    }

    public function test_claim_for_dispatch_rejects_not_due_deferred_and_failed_rows(): void
    {
        [$subscription] = $this->createSubscriptionAndIssue();
        $futureIssue = $this->createIssue($subscription->plan_id, 2, '+1 day');
        $deferredIssue = $this->createIssue($subscription->plan_id, 3, '-1 minute');
        $failedIssue = $this->createIssue($subscription->plan_id, 4, '-2 minutes');

        $future = $this->repository->createFromSchedule($subscription->id, $futureIssue);
        $deferred = $this->repository->createForSubscription(
            $subscription->id,
            $deferredIssue->id,
            new \DateTime('-1 minute'),
            new \DateTime('+1 day')
        );
        $failed = $this->repository->createFromSchedule($subscription->id, $failedIssue);
        $failed->update(['status' => SubscriptionIssueFulfilmentStatus::FAILED->value]);

        $claimed = $this->repository->claimForDispatch(
            [$future->id, $deferred->id, $failed->id],
            new \DateTime()
        );

        $this->assertSame([], $claimed);
        $this->assertNull(SubscriptionIssueFulfilment::find($future->id)->dispatched_at);
        $this->assertNull(SubscriptionIssueFulfilment::find($deferred->id)->dispatched_at);
        $this->assertNull(SubscriptionIssueFulfilment::find($failed->id)->dispatched_at);
    }

    public function test_release_dispatch_claims_only_reopens_scheduled_rows(): void
    {
        [$subscription] = $this->createSubscriptionAndIssue();
        $scheduledIssue = $this->createIssue($subscription->plan_id, 2, '-1 minute');
        $deliveredIssue = $this->createIssue($subscription->plan_id, 3, '-2 minutes');
        $scheduled = $this->repository->createFromSchedule($subscription->id, $scheduledIssue);
        $delivered = $this->repository->createFromSchedule($subscription->id, $deliveredIssue);
        $scheduled->markAsDispatched(new \DateTime());
        $delivered->markAsDispatched(new \DateTime());
        $delivered->markAsDelivered(new \DateTime());

        $released = $this->repository->releaseDispatchClaims([$scheduled->id, $delivered->id]);

        $this->assertSame(1, $released);
        $this->assertNull(SubscriptionIssueFulfilment::find($scheduled->id)->dispatched_at);
        $this->assertNotNull(SubscriptionIssueFulfilment::find($delivered->id)->dispatched_at);
    }

    public function test_rebuild_reactivates_a_superseded_fulfilment(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $fulfilment = $this->repository->createFromSchedule($subscription->id, $issue);
        $fulfilment->update([
            'status' => SubscriptionIssueFulfilmentStatus::SUPERSEDED->value,
            'attempts' => 2,
            'deferred_until' => (new \DateTime('+20 days'))->format('Y-m-d H:i:s'),
            'failed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'failure_reason' => 'Old failure',
            'skip_reason' => 'Old skip',
        ]);

        $rebuilt = $this->repository->createFromSchedule($subscription->id, $issue);

        $this->assertEquals($fulfilment->id, $rebuilt->id);
        $this->assertEquals(SubscriptionIssueFulfilmentStatus::SCHEDULED->value, $rebuilt->status);
        $this->assertEquals(0, $rebuilt->attempts);
        $this->assertNull($rebuilt->deferred_until);
        $this->assertNull($rebuilt->failed_at);
        $this->assertNull($rebuilt->failure_reason);
        $this->assertNull($rebuilt->skip_reason);
    }

    public function test_rebuild_does_not_reactivate_a_dispatched_fulfilment(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $fulfilment = $this->repository->createFromSchedule($subscription->id, $issue);
        $fulfilment->markAsDispatched(new \DateTime());
        $fulfilment->update(['status' => SubscriptionIssueFulfilmentStatus::SUPERSEDED->value]);

        $rebuilt = $this->repository->createFromSchedule($subscription->id, $issue);

        $this->assertEquals(SubscriptionIssueFulfilmentStatus::SUPERSEDED->value, $rebuilt->status);
        $this->assertNotNull($rebuilt->dispatched_at);
    }

    public function test_defer_and_release_only_affect_the_selected_subscription(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $other = $this->createSubscription($subscription->plan_id);
        $selected = $this->repository->createForSubscription($subscription->id, $issue->id);
        $untouched = $this->repository->createForSubscription($other->id, $issue->id);
        $deferredUntil = new \DateTime('+10 days');

        $count = $this->repository->deferForSubscriptionAndIssues(
            $subscription->id,
            [$issue->id],
            $deferredUntil
        );

        $selectedAfterDefer = SubscriptionIssueFulfilment::find($selected->id);
        $untouchedAfterDefer = SubscriptionIssueFulfilment::find($untouched->id);

        $this->assertEquals(1, $count);
        $this->assertEquals(
            $deferredUntil->format('Y-m-d'),
            $selectedAfterDefer->deferred_until->format('Y-m-d')
        );
        $this->assertNull($untouchedAfterDefer->deferred_until);

        $released = $this->repository->releaseDeferredForSubscription($subscription->id);
        $selectedAfterRelease = SubscriptionIssueFulfilment::find($selected->id);

        $this->assertEquals(1, $released);
        $this->assertNull($selectedAfterRelease->deferred_until);
    }

    public function test_defer_ignores_fulfilment_after_queue_handoff(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $fulfilment = $this->repository->createForSubscription($subscription->id, $issue->id);
        $fulfilment->markAsDispatched(new \DateTime());

        $count = $this->repository->deferForSubscriptionAndIssues(
            $subscription->id,
            [$issue->id],
            new \DateTime('+10 days')
        );

        $reloaded = SubscriptionIssueFulfilment::find($fulfilment->id);

        $this->assertEquals(0, $count);
        $this->assertNull($reloaded->deferred_until);
    }

    public function test_get_dispatched_subscription_ids_excludes_undispatched_rows(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $other = $this->createSubscription($subscription->plan_id);
        $dispatched = $this->repository->createForSubscription($subscription->id, $issue->id);
        $this->repository->createForSubscription($other->id, $issue->id);

        $dispatched->markAsDispatched(new \DateTime());

        $ids = $this->repository->getDispatchedSubscriptionIdsForIssue($issue->id);

        $this->assertEquals([$subscription->id], $ids);
        $this->assertTrue($this->repository->hasUndispatchedForIssue($issue->id));
    }

    public function test_future_queries_ignore_dispatched_and_superseded_rows(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $future = $this->repository->createFromSchedule($subscription->id, $issue);

        $dispatchedIssue = $this->createIssue($subscription->plan_id, 2, '+8 days');
        $dispatched = $this->repository->createFromSchedule($subscription->id, $dispatchedIssue);
        $dispatched->markAsDispatched(new \DateTime());

        $supersededIssue = $this->createIssue($subscription->plan_id, 3, '+9 days');
        $superseded = $this->repository->createFromSchedule($subscription->id, $supersededIssue);
        $superseded->update(['status' => SubscriptionIssueFulfilmentStatus::SUPERSEDED->value]);

        $rows = $this->repository->getFutureForSubscription($subscription->id);

        $this->assertEquals([$future->id], $rows->pluck('id')->toArray());
        $this->assertEquals(1, $this->repository->countFutureForSubscription($subscription->id));
        $this->assertEquals($issue->id, $this->repository->resolveFirstFutureIssueId($subscription->id));
    }

    public function test_supersede_future_only_changes_the_selected_subscription(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $other = $this->createSubscription($subscription->plan_id);
        $selected = $this->repository->createFromSchedule($subscription->id, $issue);
        $otherRow = $this->repository->createFromSchedule($other->id, $issue);

        $count = $this->repository->supersedeFutureForSubscription($subscription->id);

        $this->assertEquals(1, $count);
        $this->assertEquals(
            SubscriptionIssueFulfilmentStatus::SUPERSEDED->value,
            SubscriptionIssueFulfilment::find($selected->id)->status
        );
        $this->assertEquals(
            SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
            SubscriptionIssueFulfilment::find($otherRow->id)->status
        );
    }

    private function createSubscriptionAndIssue(): array
    {
        $plan = $this->createSubscriptionPlan();
        $subscription = $this->createSubscription($plan->id);
        $issue = $this->createIssue($plan->id, 1, '+7 days');

        return [$subscription, $issue];
    }

    private function createIssue(int $planId, int $issueNumber, string $deliveryDate): IssueDelivery
    {
        return IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_plan_id' => $planId,
            'subscription_id' => null,
            'issue_number' => $issueNumber,
            'issue_title' => 'Test Issue ' . $issueNumber,
            'status' => IssueScheduleStatus::ACTIVE->value,
            'on_sale_date' => (new \DateTime('+5 days'))->format('Y-m-d H:i:s'),
            'estimated_delivery_date' => (new \DateTime($deliveryDate))->format('Y-m-d H:i:s'),
        ]);
    }

    private function createSubscription(int $planId): Subscription
    {
        return Subscription::create([
            'member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'plan_id' => $planId,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'type' => 'paid',
            'delivery_type' => SubscriptionType::PRINTED->value,
            'start_date' => date('Y-m-d H:i:s'),
        ]);
    }
}
