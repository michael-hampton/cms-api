<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\PublicationChangeRebuildResult;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Services\Subscriptions\SubscriptionIssueDeliveryRebuildService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class SubscriptionIssueDeliveryRebuildServiceTest extends FunctionalTestCase
{
    private $scheduleRepository;
    private $fulfilmentRepository;
    private SubscriptionIssueDeliveryRebuildService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduleRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->fulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->service = new SubscriptionIssueDeliveryRebuildService(
            $this->scheduleRepository,
            $this->fulfilmentRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rebuild_for_edition_change_supersedes_existing_future_deliveries(): void
    {
        $issue = $this->issue(50);
        $this->expectEditionIssues(22, 99, 1, [$issue]);
        $this->fulfilmentRepository->shouldReceive('supersedeFutureForSubscription')->once()->with(1)->andReturn(1);
        $this->fulfilmentRepository->shouldReceive('createFromSchedule')->once()->with(1, $issue);
        $this->service->rebuildForEditionChange(1, 22, 99, 1);
        $this->assertTrue(true);
    }

    public function test_rebuild_for_edition_change_creates_same_number_of_new_deliveries_as_remaining_count(): void
    {
        $first = $this->issue(50);
        $second = $this->issue(51);
        $this->expectEditionIssues(22, 99, 2, [$first, $second]);
        $this->fulfilmentRepository->shouldReceive('supersedeFutureForSubscription')->once()->with(1)->andReturn(2);
        $this->fulfilmentRepository->shouldReceive('createFromSchedule')->once()->with(1, $first);
        $this->fulfilmentRepository->shouldReceive('createFromSchedule')->once()->with(1, $second);
        $this->service->rebuildForEditionChange(1, 22, 99, 2);
        $this->assertTrue(true);
    }

    public function test_rebuild_for_edition_change_skips_everything_when_remaining_count_is_zero(): void
    {
        $this->scheduleRepository->shouldNotReceive('findFutureIssuesForPlanStartingFromIssue');
        $this->fulfilmentRepository->shouldNotReceive('supersedeFutureForSubscription');
        $this->fulfilmentRepository->shouldNotReceive('createFromSchedule');
        $this->service->rebuildForEditionChange(1, 22, 99, 0);
        $this->assertTrue(true);
    }

    public function test_rebuild_for_edition_change_throws_when_not_enough_schedule_issues_exist(): void
    {
        $this->expectEditionIssues(22, 99, 2, [$this->issue(50)]);
        $this->fulfilmentRepository->shouldNotReceive('supersedeFutureForSubscription');
        $this->fulfilmentRepository->shouldNotReceive('createFromSchedule');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only 1 future issues found for plan #22 starting from edition #99; 2 required.');
        $this->service->rebuildForEditionChange(1, 22, 99, 2);
    }

    public function test_resolve_current_future_edition_id_returns_issue_delivery_id_when_present(): void
    {
        $this->fulfilmentRepository->shouldReceive('resolveFirstFutureIssueId')->once()->with(42)->andReturn(123);
        $this->assertSame(123, $this->service->resolveCurrentFutureEditionId(42));
    }

    public function test_resolve_current_future_edition_id_returns_id_when_issue_delivery_id_is_missing(): void
    {
        $this->fulfilmentRepository->shouldReceive('resolveFirstFutureIssueId')->once()->with(42)->andReturn(10);
        $this->assertSame(10, $this->service->resolveCurrentFutureEditionId(42));
    }

    public function test_resolve_current_future_edition_id_returns_null_when_no_future_deliveries(): void
    {
        $this->fulfilmentRepository->shouldReceive('resolveFirstFutureIssueId')->once()->with(42)->andReturn(null);
        $this->assertNull($this->service->resolveCurrentFutureEditionId(42));
    }

    public function test_rebuild_for_publication_change_supersedes_future_deliveries(): void
    {
        $issues = [$this->issue(50), $this->issue(51), $this->issue(52)];
        $this->fulfilmentRepository->shouldReceive('resolveFirstFutureIssueId')->once()->with(1)->andReturn(10);
        $this->expectPublicationIssues(99, 3, $issues);
        $this->fulfilmentRepository->shouldReceive('supersedeFutureForSubscription')->once()->with(1)->andReturn(1);
        foreach ($issues as $issue) {
            $this->fulfilmentRepository->shouldReceive('createFromSchedule')->once()->with(1, $issue);
        }
        $result = $this->service->rebuildForPublicationChange(1, 99, 3);
        $this->assertInstanceOf(PublicationChangeRebuildResult::class, $result);
        $this->assertSame(10, $result->oldEditionId);
        $this->assertSame(50, $result->newEditionId);
        $this->assertSame(3, $result->remainingIssuesTransferred);
    }

    public function test_rebuild_for_publication_change_uses_caller_supplied_remaining_count(): void
    {
        $issues = [$this->issue(50), $this->issue(51), $this->issue(52), $this->issue(53), $this->issue(54)];
        $this->fulfilmentRepository->shouldReceive('resolveFirstFutureIssueId')->once()->andReturn(10);
        $this->expectPublicationIssues(99, 5, $issues);
        $this->fulfilmentRepository->shouldReceive('supersedeFutureForSubscription')->once()->with(1)->andReturn(1);
        $this->fulfilmentRepository->shouldReceive('createFromSchedule')->times(5);
        $result = $this->service->rebuildForPublicationChange(1, 99, 5);
        $this->assertSame(5, $result->remainingIssuesTransferred);
    }

    public function test_rebuild_for_publication_change_creates_no_deliveries_when_count_is_zero(): void
    {
        $this->fulfilmentRepository->shouldReceive('resolveFirstFutureIssueId')->once()->with(1)->andReturn(10);
        $this->fulfilmentRepository->shouldReceive('supersedeFutureForSubscription')->once()->with(1)->andReturn(1);
        $this->scheduleRepository->shouldNotReceive('findFutureIssuesForPlan');
        $this->fulfilmentRepository->shouldNotReceive('createFromSchedule');
        $result = $this->service->rebuildForPublicationChange(1, 99, 0);
        $this->assertSame(10, $result->oldEditionId);
        $this->assertNull($result->newEditionId);
        $this->assertSame(0, $result->remainingIssuesTransferred);
    }

    public function test_rebuild_for_publication_change_throws_when_not_enough_future_schedule_issues_exist(): void
    {
        $this->fulfilmentRepository->shouldReceive('resolveFirstFutureIssueId')->once()->with(1)->andReturn(10);
        $this->expectPublicationIssues(99, 3, [$this->issue(50), $this->issue(51)]);
        $this->fulfilmentRepository->shouldNotReceive('supersedeFutureForSubscription');
        $this->fulfilmentRepository->shouldNotReceive('createFromSchedule');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only 2 future issues found for publication #99; 3 required.');
        $this->service->rebuildForPublicationChange(1, 99, 3);
    }

    public function test_count_remaining_issues_delegates_to_repository(): void
    {
        $this->fulfilmentRepository->shouldReceive('countFutureForSubscription')->once()->with(42)->andReturn(7);
        $this->assertEquals(7, $this->service->countRemainingIssues(42));
    }

    public function test_count_remaining_issues_returns_zero_when_none(): void
    {
        $this->fulfilmentRepository->shouldReceive('countFutureForSubscription')->once()->with(1)->andReturn(0);
        $this->assertEquals(0, $this->service->countRemainingIssues(1));
    }

    private function expectEditionIssues(int $planId, int $editionId, int $limit, array $issues): void
    {
        $this->scheduleRepository->shouldReceive('findFutureIssuesForPlanStartingFromIssue')
            ->once()->with($planId, $editionId, $limit)->andReturn(collect($issues));
    }

    private function expectPublicationIssues(int $planId, int $limit, array $issues): void
    {
        $this->scheduleRepository->shouldReceive('findFutureIssuesForPlan')
            ->once()->with($planId, Mockery::type(\DateTimeInterface::class), $limit)
            ->andReturn(collect($issues));
    }

    private function issue(int $id): IssueDelivery
    {
        $issue = new IssueDelivery();
        $issue->id = $id;
        return $issue;
    }
}
