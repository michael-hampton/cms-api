<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Services\Subscriptions\RenewalIssueSchedulingService;
use Mockery;
use PHPUnit\Framework\TestCase;

class RenewalIssueSchedulingServiceTest extends TestCase
{
    private $issueDeliveryRepository;
    private $subscriptionIssueFulfilmentRepository;
    private RenewalIssueSchedulingService $service;

    public function test_replaces_future_fulfilments_for_renewal_and_schedules_new_subscription(): void
    {
        $oldSubscription = $this->makeSubscription(10, 100);
        $newSubscription = $this->makeSubscription(20, 100);
        $newSubscription->start_date = new \DateTimeImmutable('2026-06-01 00:00:00');
        $newSubscription->end_date = new \DateTimeImmutable('2026-07-01 00:00:00');

        $issue = $this->makeIssue(500, '2026-06-15 00:00:00');

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('supersedeFutureForSubscription')
            ->once()
            ->with(10)
            ->andReturn(3);

        $this->issueDeliveryRepository
            ->shouldReceive('findAvailableEditionsForSubscriptionPlan')
            ->once()
            ->with(100, Mockery::type(\DateTimeInterface::class))
            ->andReturn(new Collection([$issue]));

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('existsForSubscriptionAndSchedule')
            ->once()
            ->with(20, 500)
            ->andReturn(false);

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('createFromSchedule')
            ->once()
            ->with(20, $issue);

        $result = $this->service->replaceFutureFulfilmentsForRenewal($oldSubscription, $newSubscription);

        $this->assertSame(3, $result['old_superseded']);
        $this->assertSame(1, $result['new_created']);
        $this->assertSame(0, $result['new_existing']);
        $this->assertSame(0, $result['new_skipped']);
    }

    public function test_schedules_renewal_issue_after_subscription_end_date(): void
    {
        $subscription = $this->makeSubscription(20, 100);
        $subscription->start_date = new \DateTimeImmutable('2026-06-01 00:00:00');
        $subscription->end_date = new \DateTimeImmutable('2026-06-30 00:00:00');

        $issue = $this->makeIssue(501, '2026-07-01 00:00:00');

        $this->issueDeliveryRepository
            ->shouldReceive('findAvailableEditionsForSubscriptionPlan')
            ->once()
            ->with(100, Mockery::type(\DateTimeInterface::class))
            ->andReturn(new Collection([$issue]));

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('existsForSubscriptionAndSchedule')
            ->once()
            ->with(20, 501)
            ->andReturn(false);

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('createFromSchedule')
            ->once()
            ->with(20, $issue);

        $result = $this->service->scheduleForSubscription($subscription);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['existing']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_extend_for_in_place_renewal_creates_issue_count_fulfilments(): void
    {
        $subscription = $this->makeSubscription(20, 100);
        $subscription->delivery_type = SubscriptionType::PRINTED->value;
        $subscription->start_date = new \DateTimeImmutable('2025-01-01 00:00:00');

        $periodStart = new \DateTimeImmutable('2026-06-01 00:00:00');
        $issue1 = $this->makeIssue(501, '2026-06-15 00:00:00');
        $issue2 = $this->makeIssue(502, '2026-07-15 00:00:00');

        $this->issueDeliveryRepository
            ->shouldReceive('findFutureIssuesForPlan')
            ->once()
            ->with(100, $periodStart, 2)
            ->andReturn(new Collection([$issue1, $issue2]));

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('existsForSubscriptionAndSchedule')
            ->twice()
            ->andReturn(false);

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('createFromSchedule')
            ->once()
            ->with(20, $issue1);
        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('createFromSchedule')
            ->once()
            ->with(20, $issue2);

        $this->subscriptionIssueFulfilmentRepository->shouldNotReceive('supersedeFutureForSubscription');

        $result = $this->service->extendForInPlaceRenewal($subscription, $periodStart, 2);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['existing']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_extend_for_in_place_renewal_is_idempotent_for_existing_fulfilments(): void
    {
        $subscription = $this->makeSubscription(20, 100);
        $subscription->delivery_type = SubscriptionType::PRINTED->value;
        $subscription->start_date = new \DateTimeImmutable('2025-01-01 00:00:00');

        $periodStart = new \DateTimeImmutable('2026-06-01 00:00:00');
        $issue = $this->makeIssue(501, '2026-06-15 00:00:00');

        $this->issueDeliveryRepository
            ->shouldReceive('findFutureIssuesForPlan')
            ->once()
            ->with(100, $periodStart, 1)
            ->andReturn(new Collection([$issue]));

        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('existsForSubscriptionAndSchedule')
            ->once()
            ->with(20, 501)
            ->andReturn(true);

        $this->subscriptionIssueFulfilmentRepository
            ->shouldNotReceive('createFromSchedule');

        $result = $this->service->extendForInPlaceRenewal($subscription, $periodStart, 1);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['existing']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_extend_for_in_place_renewal_skips_non_print_subscriptions(): void
    {
        $subscription = $this->makeSubscription(20, 100);
        $subscription->delivery_type = SubscriptionType::DIGITAL->value;

        $this->issueDeliveryRepository->shouldNotReceive('findFutureIssuesForPlan');
        $this->subscriptionIssueFulfilmentRepository->shouldNotReceive('createFromSchedule');

        $result = $this->service->extendForInPlaceRenewal(
            $subscription,
            new \DateTimeImmutable('2026-06-01 00:00:00'),
            3,
        );

        $this->assertSame(['created' => 0, 'existing' => 0, 'skipped' => 0], $result);
    }

    public function test_extend_for_in_place_renewal_noops_for_zero_issue_count(): void
    {
        $subscription = $this->makeSubscription(20, 100);
        $subscription->delivery_type = SubscriptionType::PRINTED->value;

        $this->issueDeliveryRepository->shouldNotReceive('findFutureIssuesForPlan');

        $result = $this->service->extendForInPlaceRenewal(
            $subscription,
            new \DateTimeImmutable('2026-06-01 00:00:00'),
            0,
        );

        $this->assertSame(['created' => 0, 'existing' => 0, 'skipped' => 0], $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->subscriptionIssueFulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);

        $this->service = new RenewalIssueSchedulingService(
            $this->issueDeliveryRepository,
            $this->subscriptionIssueFulfilmentRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeSubscription(int $id, int $planId): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $id;
        $subscription->plan_id = $planId;

        return $subscription;
    }

    private function makeIssue(int $id, string $onSaleDate): IssueDelivery
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = $id;
        $issue->subscription_plan_id = 100;
        $issue->status = IssueScheduleStatus::ACTIVE->value;
        $issue->on_sale_date = new \DateTimeImmutable($onSaleDate);
        $issue->estimated_delivery_date = null;

        return $issue;
    }
}
