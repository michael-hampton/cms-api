<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\SubscriptionIssueFulfilmentStatus;
use App\Models\Subscription;
use App\Models\SubscriptionIssueFulfilment;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionIssueFulfilmentSuspensionRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private SubscriptionIssueFulfilmentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionIssueFulfilmentRepository();
    }

    /** @return array{0: Subscription, 1: SubscriptionIssueFulfilment} */
    private function createPendingFulfilment(): array
    {
        $plan = $this->createSubscriptionPlan();
        $subscription = $this->createSubscription(['plan_id' => $plan->id]);
        $issue = $this->createIssueDelivery(['subscription_plan_id' => $plan->id]);

        $fulfilment = SubscriptionIssueFulfilment::create([
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issue->id,
            'status' => SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
            'attempts' => 0,
        ]);

        return [$subscription, $fulfilment];
    }

    public function test_suspend_pending_moves_scheduled_undispatched_rows_to_suspended(): void
    {
        [$subscription, $fulfilment] = $this->createPendingFulfilment();

        $count = $this->repository->suspendPendingForSubscription($subscription->id, 'payment_failed');

        $this->assertSame(1, $count);
        $fresh = SubscriptionIssueFulfilment::find($fulfilment->id);
        $this->assertSame(SubscriptionIssueFulfilmentStatus::SUSPENDED->value, $fresh->status);
        $this->assertSame('payment_failed', $fresh->suspension_reason);
    }

    public function test_suspend_pending_does_not_touch_dispatched_rows(): void
    {
        [$subscription, $fulfilment] = $this->createPendingFulfilment();
        $fulfilment->update(['dispatched_at' => now()]);

        $count = $this->repository->suspendPendingForSubscription($subscription->id, 'payment_failed');

        $this->assertSame(0, $count);
        $fresh = SubscriptionIssueFulfilment::find($fulfilment->id);
        $this->assertSame(SubscriptionIssueFulfilmentStatus::SCHEDULED->value, $fresh->status);
    }

    public function test_release_suspended_returns_rows_to_scheduled(): void
    {
        [$subscription, $fulfilment] = $this->createPendingFulfilment();
        $this->repository->suspendPendingForSubscription($subscription->id, 'payment_failed');

        $count = $this->repository->releaseSuspendedForSubscription($subscription->id);

        $this->assertSame(1, $count);
        $fresh = SubscriptionIssueFulfilment::find($fulfilment->id);
        $this->assertSame(SubscriptionIssueFulfilmentStatus::SCHEDULED->value, $fresh->status);
        $this->assertNull($fresh->suspension_reason);
    }

    public function test_cancel_pending_moves_scheduled_undispatched_rows_to_cancelled(): void
    {
        [$subscription, $fulfilment] = $this->createPendingFulfilment();

        $count = $this->repository->cancelPendingForSubscription($subscription->id);

        $this->assertSame(1, $count);
        $fresh = SubscriptionIssueFulfilment::find($fulfilment->id);
        $this->assertSame(SubscriptionIssueFulfilmentStatus::CANCELLED->value, $fresh->status);
    }

    public function test_cancel_pending_also_cancels_paused_and_suspended_rows(): void
    {
        $plan = $this->createSubscriptionPlan();
        $subscription = $this->createSubscription(['plan_id' => $plan->id]);

        $statuses = [
            SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
            SubscriptionIssueFulfilmentStatus::PAUSED->value,
            SubscriptionIssueFulfilmentStatus::SUSPENDED->value,
        ];

        $fulfilmentIds = [];
        foreach ($statuses as $status) {
            $issue = $this->createIssueDelivery(['subscription_plan_id' => $plan->id]);
            $fulfilment = SubscriptionIssueFulfilment::create([
                'subscription_id' => $subscription->id,
                'issue_delivery_id' => $issue->id,
                'status' => $status,
                'attempts' => 0,
            ]);
            $fulfilmentIds[] = $fulfilment->id;
        }

        $count = $this->repository->cancelPendingForSubscription($subscription->id);

        $this->assertSame(3, $count);
        foreach ($fulfilmentIds as $id) {
            $this->assertSame(
                SubscriptionIssueFulfilmentStatus::CANCELLED->value,
                SubscriptionIssueFulfilment::find($id)->status
            );
        }
    }

    public function test_pause_pending_moves_scheduled_undispatched_rows_to_paused(): void
    {
        [$subscription, $fulfilment] = $this->createPendingFulfilment();

        $count = $this->repository->pausePendingForSubscription($subscription->id);

        $this->assertSame(1, $count);
        $this->assertSame(1, $this->repository->countPausedForSubscription($subscription->id));
    }

    public function test_supersede_paused_moves_paused_rows_to_superseded(): void
    {
        [$subscription, $fulfilment] = $this->createPendingFulfilment();
        $this->repository->pausePendingForSubscription($subscription->id);

        $count = $this->repository->supersedePausedForSubscription($subscription->id);

        $this->assertSame(1, $count);
        $fresh = SubscriptionIssueFulfilment::find($fulfilment->id);
        $this->assertSame(SubscriptionIssueFulfilmentStatus::SUPERSEDED->value, $fresh->status);
        $this->assertSame(0, $this->repository->countPausedForSubscription($subscription->id));
    }

    public function test_first_delivered_at_returns_null_when_nothing_delivered(): void
    {
        [$subscription] = $this->createPendingFulfilment();

        $this->assertNull($this->repository->firstDeliveredAt($subscription->id));
        $this->assertSame(0, $this->repository->countDeliveredForSubscription($subscription->id));
    }

    public function test_first_delivered_at_returns_earliest_delivered_timestamp(): void
    {
        $plan = $this->createSubscriptionPlan();
        $subscription = $this->createSubscription(['plan_id' => $plan->id]);

        $later = $this->createIssueDelivery(['subscription_plan_id' => $plan->id]);
        SubscriptionIssueFulfilment::create([
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $later->id,
            'status' => SubscriptionIssueFulfilmentStatus::DELIVERED->value,
            'delivered_at' => new \DateTime('2026-06-15'),
            'attempts' => 1,
        ]);

        $earlier = $this->createIssueDelivery(['subscription_plan_id' => $plan->id]);
        SubscriptionIssueFulfilment::create([
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $earlier->id,
            'status' => SubscriptionIssueFulfilmentStatus::DELIVERED->value,
            'delivered_at' => new \DateTime('2026-05-01'),
            'attempts' => 1,
        ]);

        $firstDelivered = $this->repository->firstDeliveredAt($subscription->id);

        $this->assertNotNull($firstDelivered);
        $this->assertSame('2026-05-01', $firstDelivered->format('Y-m-d'));
        $this->assertSame(2, $this->repository->countDeliveredForSubscription($subscription->id));
    }
}
