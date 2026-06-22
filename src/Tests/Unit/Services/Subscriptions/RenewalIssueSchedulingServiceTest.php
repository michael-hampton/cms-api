<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
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
