<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveredStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\IssuesDelivered;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Subscriptions\IssueFulfilmentPlanner;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class IssueFulfilmentPlannerTest extends FunctionalTestCase
{
    private $repository;
    private IssueFulfilmentPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(IssuesDeliveredRepository::class);
        $this->planner = new IssueFulfilmentPlanner($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_classifies_and_claims_digital_and_print_fulfilments(): void
    {
        $issue = $this->makeIssue();
        $digital = $this->makeSubscription(1, SubscriptionType::DIGITAL->value);
        $print = $this->makeSubscription(2, SubscriptionType::PRINTED->value);
        $digitalFulfilment = $this->makeFulfilment(10);
        $printFulfilment = $this->makeFulfilment(11);

        $this->expectFulfilment($digital, $issue, null, $digitalFulfilment);
        $this->expectFulfilment($print, $issue, null, $printFulfilment);
        $this->repository->shouldReceive('claimForDispatch')->once()->with([10], Mockery::type(\DateTimeInterface::class))->andReturn([10]);
        $this->repository->shouldReceive('claimForDispatch')->once()->with([11], Mockery::type(\DateTimeInterface::class))->andReturn([11]);

        $result = $this->planner->plan($issue, collect([$digital, $print]));

        $this->assertSame([10], $result['digital_ids']);
        $this->assertSame([11], $result['print_ids']);
        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['claim_conflicts']);
    }

    public function test_reuses_existing_fulfilment_and_repairs_it_through_repository(): void
    {
        $issue = $this->makeIssue();
        $subscription = $this->makeSubscription(1, SubscriptionType::DIGITAL->value);
        $fulfilment = $this->makeFulfilment(10);

        $this->expectFulfilment($subscription, $issue, $fulfilment, $fulfilment);
        $this->repository->shouldReceive('claimForDispatch')->once()->with([10], Mockery::type(\DateTimeInterface::class))->andReturn([10]);
        $this->repository->shouldReceive('claimForDispatch')->once()->with([], Mockery::type(\DateTimeInterface::class))->andReturn([]);

        $result = $this->planner->plan($issue, collect([$subscription]));

        $this->assertSame(0, $result['created']);
        $this->assertSame([10], $result['digital_ids']);
    }

    public function test_counts_deferred_and_not_due_separately(): void
    {
        $issue = $this->makeIssue();
        $deferredSubscription = $this->makeSubscription(1, SubscriptionType::DIGITAL->value);
        $notDueSubscription = $this->makeSubscription(2, SubscriptionType::PRINTED->value);
        $deferred = $this->makeFulfilment(10, new \DateTime('-1 minute'), new \DateTime('+1 day'));
        $notDue = $this->makeFulfilment(11, new \DateTime('+1 day'));

        $this->expectFulfilment($deferredSubscription, $issue, $deferred, $deferred);
        $this->expectFulfilment($notDueSubscription, $issue, $notDue, $notDue);
        $this->repository->shouldReceive('claimForDispatch')->twice()->with([], Mockery::type(\DateTimeInterface::class))->andReturn([]);

        $result = $this->planner->plan($issue, collect([$deferredSubscription, $notDueSubscription]));

        $this->assertSame(1, $result['deferred']);
        $this->assertSame(1, $result['not_due']);
        $this->assertSame(0, $result['already_dispatched']);
    }

    public function test_counts_dispatched_and_non_scheduled_rows_without_claiming_them(): void
    {
        $issue = $this->makeIssue();
        $dispatchedSubscription = $this->makeSubscription(1, SubscriptionType::DIGITAL->value);
        $failedSubscription = $this->makeSubscription(2, SubscriptionType::PRINTED->value);
        $dispatched = $this->makeFulfilment(10);
        $dispatched->dispatched_at = new \DateTime();
        $failed = $this->makeFulfilment(11);
        $failed->status = IssueDeliveredStatus::FAILED->value;

        $this->expectFulfilment($dispatchedSubscription, $issue, $dispatched, $dispatched);
        $this->expectFulfilment($failedSubscription, $issue, $failed, $failed);
        $this->repository->shouldReceive('claimForDispatch')->twice()->with([], Mockery::type(\DateTimeInterface::class))->andReturn([]);

        $result = $this->planner->plan($issue, collect([$dispatchedSubscription, $failedSubscription]));

        $this->assertSame(1, $result['already_dispatched']);
        $this->assertSame(1, $result['non_dispatchable_status']);
    }

    public function test_reports_claim_conflicts_when_another_worker_claims_first(): void
    {
        $issue = $this->makeIssue();
        $subscription = $this->makeSubscription(1, SubscriptionType::DIGITAL->value);
        $fulfilment = $this->makeFulfilment(10);

        $this->expectFulfilment($subscription, $issue, null, $fulfilment);
        $this->repository->shouldReceive('claimForDispatch')->once()->with([10], Mockery::type(\DateTimeInterface::class))->andReturn([]);
        $this->repository->shouldReceive('claimForDispatch')->once()->with([], Mockery::type(\DateTimeInterface::class))->andReturn([]);

        $result = $this->planner->plan($issue, collect([$subscription]));

        $this->assertSame([], $result['digital_ids']);
        $this->assertSame(1, $result['claim_conflicts']);
    }

    private function expectFulfilment(
        Subscription $subscription,
        IssueDelivery $issue,
        ?IssuesDelivered $existing,
        IssuesDelivered $returned
    ): void {
        $this->repository->shouldReceive('findBySubscriptionAndSchedule')
            ->once()->with($subscription->id, $issue->id)->andReturn($existing);
        $this->repository->shouldReceive('createForSubscription')
            ->once()->with($subscription->id, $issue->id, $issue->estimated_delivery_date, Mockery::any())
            ->andReturn($returned);
    }

    private function makeIssue(): IssueDelivery
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 50;
        $issue->estimated_delivery_date = new \DateTime('-1 minute');
        return $issue;
    }

    private function makeSubscription(int $id, string $deliveryType): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $id;
        $subscription->delivery_type = $deliveryType;
        $subscription->delivery_paused = false;
        return $subscription;
    }

    private function makeFulfilment(
        int $id,
        ?\DateTimeInterface $scheduledFor = null,
        ?\DateTimeInterface $deferredUntil = null
    ): IssuesDelivered {
        $fulfilment = Mockery::mock(IssuesDelivered::class)->makePartial();
        $fulfilment->id = $id;
        $fulfilment->status = IssueDeliveredStatus::SCHEDULED->value;
        $fulfilment->scheduled_for = $scheduledFor ?? new \DateTime('-1 minute');
        $fulfilment->deferred_until = $deferredUntil;
        $fulfilment->dispatched_at = null;
        return $fulfilment;
    }
}
