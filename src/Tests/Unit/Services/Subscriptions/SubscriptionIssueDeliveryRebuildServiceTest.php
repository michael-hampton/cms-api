<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\PublicationChangeRebuildResult;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Services\Subscriptions\SubscriptionIssueDeliveryRebuildService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionIssueDeliveryRebuildServiceTest extends FunctionalTestCase
{
    private IssueDeliveryRepository&MockInterface $issueDeliveryRepository;
    private SubscriptionIssueDeliveryRebuildService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->service = new SubscriptionIssueDeliveryRebuildService(
            $this->issueDeliveryRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── rebuildForEditionChange ───────────────────────────────────────────────

    public function test_rebuild_for_edition_change_supersedes_existing_future_deliveries(): void
    {
        $subscriptionId = 1;
        $planId = 22;
        $startingEditionId = 99;

        $futureDelivery = $this->makeIssueDelivery(10);
        $scheduleIssue = $this->makeIssueDelivery(50);

        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForSubscription')
            ->once()
            ->with($subscriptionId, ['pending', 'scheduled', 'not_dispatched'])
            ->andReturn([$futureDelivery]);

        $this->issueDeliveryRepository
            ->shouldReceive('supersedeManyByIds')
            ->once()
            ->with([10]);

        $this->issueDeliveryRepository
            ->shouldReceive('findFutureIssuesForPlanStartingFromIssue')
            ->once()
            ->with($planId, $startingEditionId, 1)
            ->andReturn(collect([$scheduleIssue]));

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->once()
            ->with($subscriptionId, $planId, $scheduleIssue);

        $this->service->rebuildForEditionChange(
            subscriptionId: $subscriptionId,
            subscriptionPlanId: $planId,
            startingEditionId: $startingEditionId,
            remainingIssueCount: 1,
        );

        $this->assertTrue(true);
    }

    public function test_rebuild_for_edition_change_creates_same_number_of_new_deliveries_as_remaining_count(): void
    {
        $subscriptionId = 1;
        $planId = 22;
        $startingEditionId = 99;

        $futureDeliveryA = $this->makeIssueDelivery(10);
        $futureDeliveryB = $this->makeIssueDelivery(11);

        $scheduleIssueA = $this->makeIssueDelivery(50);
        $scheduleIssueB = $this->makeIssueDelivery(51);

        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForSubscription')
            ->once()
            ->with($subscriptionId, ['pending', 'scheduled', 'not_dispatched'])
            ->andReturn([$futureDeliveryA, $futureDeliveryB]);

        $this->issueDeliveryRepository
            ->shouldReceive('supersedeManyByIds')
            ->once()
            ->with([10, 11]);

        $this->issueDeliveryRepository
            ->shouldReceive('findFutureIssuesForPlanStartingFromIssue')
            ->once()
            ->with($planId, $startingEditionId, 2)
            ->andReturn(collect([$scheduleIssueA, $scheduleIssueB]));

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->once()
            ->with($subscriptionId, $planId, $scheduleIssueA);

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->once()
            ->with($subscriptionId, $planId, $scheduleIssueB);

        $this->service->rebuildForEditionChange(
            subscriptionId: $subscriptionId,
            subscriptionPlanId: $planId,
            startingEditionId: $startingEditionId,
            remainingIssueCount: 2,
        );

        $this->assertTrue(true);
    }

    public function test_rebuild_for_edition_change_skips_everything_when_remaining_count_is_zero(): void
    {
        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForSubscription')
            ->never();

        $this->issueDeliveryRepository
            ->shouldReceive('supersedeManyByIds')
            ->never();

        $this->issueDeliveryRepository
            ->shouldReceive('findFutureIssuesForPlanStartingFromIssue')
            ->never();

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->never();

        $this->service->rebuildForEditionChange(
            subscriptionId: 1,
            subscriptionPlanId: 22,
            startingEditionId: 99,
            remainingIssueCount: 0,
        );

        $this->assertTrue(true);
    }

    public function test_rebuild_for_edition_change_throws_when_not_enough_schedule_issues_exist(): void
    {
        $subscriptionId = 1;
        $planId = 22;
        $startingEditionId = 99;

        $futureDeliveryA = $this->makeIssueDelivery(10);
        $futureDeliveryB = $this->makeIssueDelivery(11);

        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForSubscription')
            ->once()
            ->with($subscriptionId, ['pending', 'scheduled', 'not_dispatched'])
            ->andReturn([$futureDeliveryA, $futureDeliveryB]);

        $this->issueDeliveryRepository
            ->shouldReceive('supersedeManyByIds')
            ->once()
            ->with([10, 11]);

        $this->issueDeliveryRepository
            ->shouldReceive('findFutureIssuesForPlanStartingFromIssue')
            ->once()
            ->with($planId, $startingEditionId, 2)
            ->andReturn(collect([
                $this->makeIssueDelivery(50),
            ]));

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Only 1 future issues found for plan #22 starting from edition #99; 2 required.'
        );

        $this->service->rebuildForEditionChange(
            subscriptionId: $subscriptionId,
            subscriptionPlanId: $planId,
            startingEditionId: $startingEditionId,
            remainingIssueCount: 2,
        );
    }

    public function test_resolve_current_future_edition_id_returns_issue_delivery_id_when_present(): void
    {
        $delivery = $this->makeIssueDelivery(10);
        $delivery->issue_delivery_id = 123;

        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForPlan')
            ->once()
            ->with(22, ['pending', 'scheduled', 'not_dispatched'])
            ->andReturn([$delivery]);

        $result = $this->service->resolveCurrentFutureEditionId(22);

        $this->assertSame(123, $result);
    }

    public function test_resolve_current_future_edition_id_returns_id_when_issue_delivery_id_is_missing(): void
    {
        $delivery = $this->makeIssueDelivery(10);

        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForPlan')
            ->once()
            ->with(22, ['pending', 'scheduled', 'not_dispatched'])
            ->andReturn([$delivery]);

        $result = $this->service->resolveCurrentFutureEditionId(22);

        $this->assertSame(10, $result);
    }

    public function test_resolve_current_future_edition_id_returns_null_when_no_future_deliveries(): void
    {
        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForPlan')
            ->once()
            ->with(22, ['pending', 'scheduled', 'not_dispatched'])
            ->andReturn([]);

        $result = $this->service->resolveCurrentFutureEditionId(22);

        $this->assertNull($result);
    }

    // ── rebuildForPublicationChange ───────────────────────────────────────────

    public function test_rebuild_for_publication_change_supersedes_future_deliveries(): void
    {
        $subscriptionId = 1;
        $newPublicationId = 99;
        $futureDelivery = $this->makeIssueDelivery(10);
        $scheduleIssueA = $this->makeIssueDelivery(50);
        $scheduleIssueB = $this->makeIssueDelivery(51);
        $scheduleIssueC = $this->makeIssueDelivery(52);

        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForSubscription')
            ->once()
            ->with($subscriptionId, ['pending', 'scheduled', 'not_dispatched'])
            ->andReturn([$futureDelivery]);

        $this->issueDeliveryRepository
            ->shouldReceive('supersedeManyByIds')
            ->once()
            ->with([10]);

        $this->issueDeliveryRepository
            ->shouldReceive('findFutureIssuesForPlan')
            ->once()
            ->with(
                $newPublicationId,
                Mockery::type(\DateTimeInterface::class),
                3
            )
            ->andReturn(collect([
                $scheduleIssueA,
                $scheduleIssueB,
                $scheduleIssueC,
            ]));

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->once()
            ->with($subscriptionId, $newPublicationId, $scheduleIssueA);

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->once()
            ->with($subscriptionId, $newPublicationId, $scheduleIssueB);

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->once()
            ->with($subscriptionId, $newPublicationId, $scheduleIssueC);

        $result = $this->service->rebuildForPublicationChange(
            $subscriptionId,
            $newPublicationId,
            3
        );

        $this->assertInstanceOf(PublicationChangeRebuildResult::class, $result);
        $this->assertSame(10, $result->oldEditionId);
        $this->assertSame(50, $result->newEditionId);
        $this->assertSame(3, $result->remainingIssuesTransferred);
    }

    public function test_rebuild_for_publication_change_uses_caller_supplied_remaining_count(): void
    {
        $subscriptionId = 1;
        $newPublicationId = 99;

        $scheduleIssues = collect([
            $this->makeIssueDelivery(50),
            $this->makeIssueDelivery(51),
            $this->makeIssueDelivery(52),
            $this->makeIssueDelivery(53),
            $this->makeIssueDelivery(54),
        ]);

        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForSubscription')
            ->once()
            ->andReturn([$this->makeIssueDelivery(10)]);

        $this->issueDeliveryRepository
            ->shouldReceive('supersedeManyByIds')
            ->once()
            ->with([10]);

        $this->issueDeliveryRepository
            ->shouldReceive('findFutureIssuesForPlan')
            ->once()
            ->with(
                $newPublicationId,
                Mockery::type(\DateTimeInterface::class),
                5
            )
            ->andReturn($scheduleIssues);

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->times(5);

        $result = $this->service->rebuildForPublicationChange(
            $subscriptionId,
            $newPublicationId,
            5
        );

        $this->assertSame(10, $result->oldEditionId);
        $this->assertSame(50, $result->newEditionId);
        $this->assertSame(5, $result->remainingIssuesTransferred);
    }

    public function test_rebuild_for_publication_change_creates_no_deliveries_when_count_is_zero(): void
    {
        $futureDelivery = $this->makeIssueDelivery(10);

        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForSubscription')
            ->once()
            ->andReturn([$futureDelivery]);

        $this->issueDeliveryRepository
            ->shouldReceive('supersedeManyByIds')
            ->once()
            ->with([10]);

        $this->issueDeliveryRepository
            ->shouldReceive('findFutureIssuesForPlan')
            ->never();

        $this->issueDeliveryRepository
            ->shouldReceive('getUpcomingScheduleIssues')
            ->never();

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->never();

        $result = $this->service->rebuildForPublicationChange(1, 99, 0);

        $this->assertSame(10, $result->oldEditionId);
        $this->assertNull($result->newEditionId);
        $this->assertSame(0, $result->remainingIssuesTransferred);
    }

    public function test_rebuild_for_publication_change_throws_when_not_enough_future_schedule_issues_exist(): void
    {
        $this->issueDeliveryRepository
            ->shouldReceive('getFutureDeliveriesForSubscription')
            ->once()
            ->andReturn([$this->makeIssueDelivery(10)]);

        $this->issueDeliveryRepository
            ->shouldReceive('supersedeManyByIds')
            ->once()
            ->with([10]);

        $this->issueDeliveryRepository
            ->shouldReceive('findFutureIssuesForPlan')
            ->once()
            ->with(
                99,
                Mockery::type(\DateTimeInterface::class),
                3
            )
            ->andReturn(collect([
                $this->makeIssueDelivery(50),
                $this->makeIssueDelivery(51),
            ]));

        $this->issueDeliveryRepository
            ->shouldReceive('createFulfilmentFromSchedule')
            ->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only 2 future issues found for publication #99; 3 required.');

        $this->service->rebuildForPublicationChange(1, 99, 3);
    }

    // ── countRemainingIssues ──────────────────────────────────────────────────

    public function test_count_remaining_issues_delegates_to_repository(): void
    {
        $this->issueDeliveryRepository
            ->shouldReceive('countFutureForSubscription')
            ->once()
            ->with(42, ['pending', 'scheduled', 'not_dispatched'])
            ->andReturn(7);

        $result = $this->service->countRemainingIssues(42);

        $this->assertEquals(7, $result);
    }

    public function test_count_remaining_issues_returns_zero_when_none(): void
    {
        $this->issueDeliveryRepository
            ->shouldReceive('countFutureForSubscription')
            ->once()
            ->andReturn(0);

        $result = $this->service->countRemainingIssues(1);

        $this->assertEquals(0, $result);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function makeIssueDelivery(int $id): IssueDelivery
    {
        $delivery     = new IssueDelivery();
        $delivery->id = $id;

        return $delivery;
    }
}
