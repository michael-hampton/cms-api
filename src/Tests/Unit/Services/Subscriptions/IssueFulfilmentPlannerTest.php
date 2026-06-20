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

    public function test_creates_and_classifies_digital_and_print_fulfilments(): void
    {
        $issue = $this->makeIssue();
        $digital = $this->makeSubscription(1, SubscriptionType::DIGITAL->value);
        $print = $this->makeSubscription(2, SubscriptionType::PRINTED->value);
        $digitalFulfilment = $this->makeFulfilment(10, true);
        $printFulfilment = $this->makeFulfilment(11, true);

        $this->repository->shouldReceive('findBySubscriptionAndSchedule')->with(1, 50)->once()->andReturn(null);
        $this->repository->shouldReceive('findBySubscriptionAndSchedule')->with(2, 50)->once()->andReturn(null);
        $this->repository
            ->shouldReceive('createForSubscription')
            ->with(1, 50, $issue->estimated_delivery_date, null)
            ->once()
            ->andReturn($digitalFulfilment);
        $this->repository
            ->shouldReceive('createForSubscription')
            ->with(2, 50, $issue->estimated_delivery_date, null)
            ->once()
            ->andReturn($printFulfilment);

        $result = $this->planner->plan($issue, collect([$digital, $print]));

        $this->assertEquals([10], $result['digital_ids']);
        $this->assertEquals([11], $result['print_ids']);
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['deferred']);
    }

    public function test_reuses_existing_fulfilment_idempotently(): void
    {
        $issue = $this->makeIssue();
        $subscription = $this->makeSubscription(1, SubscriptionType::DIGITAL->value);
        $fulfilment = $this->makeFulfilment(10, true);

        $this->repository
            ->shouldReceive('findBySubscriptionAndSchedule')
            ->with(1, 50)
            ->once()
            ->andReturn($fulfilment);
        $this->repository->shouldNotReceive('createForSubscription');

        $result = $this->planner->plan($issue, collect([$subscription]));

        $this->assertEquals([10], $result['digital_ids']);
        $this->assertEquals(0, $result['created']);
    }

    public function test_defers_fulfilment_when_issue_falls_inside_pause_window(): void
    {
        $issue = $this->makeIssue(new \DateTime('+10 days'));
        $subscription = $this->makeSubscription(1, SubscriptionType::PRINTED->value);
        $subscription->delivery_paused = true;
        $subscription->delivery_pause_start = new \DateTime('+5 days');
        $subscription->delivery_pause_end = new \DateTime('+15 days');
        $fulfilment = $this->makeFulfilment(10, false);

        $this->repository->shouldReceive('findBySubscriptionAndSchedule')->with(1, 50)->once()->andReturn(null);
        $this->repository
            ->shouldReceive('createForSubscription')
            ->with(1, 50, $issue->estimated_delivery_date, Mockery::on(function ($date) use ($subscription) {
                return $date instanceof \DateTimeInterface
                    && $date->format('Y-m-d') === (clone $subscription->delivery_pause_end)
                        ->modify('+1 day')
                        ->format('Y-m-d');
            }))
            ->once()
            ->andReturn($fulfilment);

        $result = $this->planner->plan($issue, collect([$subscription]));

        $this->assertSame([], $result['print_ids']);
        $this->assertEquals(1, $result['deferred']);
    }

    public function test_does_not_defer_issue_outside_pause_window(): void
    {
        $issue = $this->makeIssue(new \DateTime('+20 days'));
        $subscription = $this->makeSubscription(1, SubscriptionType::PRINTED->value);
        $subscription->delivery_paused = true;
        $subscription->delivery_pause_start = new \DateTime('+5 days');
        $subscription->delivery_pause_end = new \DateTime('+15 days');
        $fulfilment = $this->makeFulfilment(10, true);

        $this->repository->shouldReceive('findBySubscriptionAndSchedule')->with(1, 50)->once()->andReturn(null);
        $this->repository
            ->shouldReceive('createForSubscription')
            ->with(1, 50, $issue->estimated_delivery_date, null)
            ->once()
            ->andReturn($fulfilment);

        $result = $this->planner->plan($issue, collect([$subscription]));

        $this->assertEquals([10], $result['print_ids']);
        $this->assertEquals(0, $result['deferred']);
    }

    private function makeIssue(?\DateTime $date = null): IssueDelivery
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 50;
        $issue->estimated_delivery_date = $date ?? new \DateTime('-1 minute');

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

    private function makeFulfilment(int $id, bool $dispatchable): IssuesDelivered
    {
        $fulfilment = Mockery::mock(IssuesDelivered::class)->makePartial();
        $fulfilment->id = $id;
        $fulfilment->status = IssueDeliveredStatus::SCHEDULED->value;
        $fulfilment->shouldReceive('canDispatchAt')->once()->andReturn($dispatchable);

        return $fulfilment;
    }
}
