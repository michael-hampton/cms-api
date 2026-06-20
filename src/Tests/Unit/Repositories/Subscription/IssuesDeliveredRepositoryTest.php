<?php

namespace App\Tests\Unit\Repositories\Subscription;

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

    private function createSubscriptionAndIssue(): array
    {
        $plan = $this->createSubscriptionPlan();
        $subscription = $this->createSubscription($plan->id);
        $issue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'subscription_id' => null,
            'issue_number' => 1,
            'issue_title' => 'Test Issue',
            'status' => IssueScheduleStatus::ACTIVE->value,
            'on_sale_date' => (new \DateTime('+5 days'))->format('Y-m-d H:i:s'),
            'estimated_delivery_date' => (new \DateTime('+7 days'))->format('Y-m-d H:i:s'),
        ]);

        return [$subscription, $issue];
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
