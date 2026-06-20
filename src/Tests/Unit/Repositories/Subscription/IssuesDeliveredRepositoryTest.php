<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\IssueDeliveredStatus;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\IssuesDelivered;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class IssuesDeliveredRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private IssuesDeliveredRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new IssuesDeliveredRepository();
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

    public function test_rebuild_reactivates_a_superseded_fulfilment(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $fulfilment = $this->repository->createFromSchedule($subscription->id, $issue);
        $fulfilment->update([
            'status' => IssueDeliveredStatus::SUPERSEDED->value,
            'attempts' => 2,
            'deferred_until' => (new \DateTime('+20 days'))->format('Y-m-d H:i:s'),
            'failed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'failure_reason' => 'Old failure',
            'skip_reason' => 'Old skip',
        ]);

        $rebuilt = $this->repository->createFromSchedule($subscription->id, $issue);

        $this->assertEquals($fulfilment->id, $rebuilt->id);
        $this->assertEquals(IssueDeliveredStatus::SCHEDULED->value, $rebuilt->status);
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
        $fulfilment->update(['status' => IssueDeliveredStatus::SUPERSEDED->value]);

        $rebuilt = $this->repository->createFromSchedule($subscription->id, $issue);

        $this->assertEquals(IssueDeliveredStatus::SUPERSEDED->value, $rebuilt->status);
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

        $selectedAfterDefer = IssuesDelivered::find($selected->id);
        $untouchedAfterDefer = IssuesDelivered::find($untouched->id);

        $this->assertEquals(1, $count);
        $this->assertEquals(
            $deferredUntil->format('Y-m-d'),
            $selectedAfterDefer->deferred_until->format('Y-m-d')
        );
        $this->assertNull($untouchedAfterDefer->deferred_until);

        $released = $this->repository->releaseDeferredForSubscription($subscription->id);
        $selectedAfterRelease = IssuesDelivered::find($selected->id);

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

        $reloaded = IssuesDelivered::find($fulfilment->id);

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
        $superseded->update(['status' => IssueDeliveredStatus::SUPERSEDED->value]);

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
            IssueDeliveredStatus::SUPERSEDED->value,
            IssuesDelivered::find($selected->id)->status
        );
        $this->assertEquals(
            IssueDeliveredStatus::SCHEDULED->value,
            IssuesDelivered::find($otherRow->id)->status
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
        ]);
    }
}
