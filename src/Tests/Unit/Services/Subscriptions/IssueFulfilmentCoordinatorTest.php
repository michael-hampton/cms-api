<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Subscriptions\IssueFulfilmentDispatchCoordinator;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class IssueFulfilmentCoordinatorTest extends FunctionalTestCase
{
    private $repository;
    private $logger;
    private IssueFulfilmentDispatchCoordinator $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(IssuesDeliveredRepository::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->service = new IssueFulfilmentDispatchCoordinator($this->repository, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_marks_issue_complete_when_no_fulfilments_remain(): void
    {
        $issue = $this->makeIssue();
        $this->repository->shouldReceive('hasUndispatchedForIssue')->with(50)->once()->andReturn(false);
        $this->repository->shouldNotReceive('markDispatched');
        $issue->shouldReceive('markDispatched')->once();

        $result = $this->service->dispatch($issue, $this->plan());

        $this->assertSame(0, $result['digital_dispatches']);
        $this->assertSame(0, $result['print_dispatches']);
    }

    public function test_keeps_issue_open_while_deferred_fulfilments_remain(): void
    {
        $issue = $this->makeIssue();
        $this->repository->shouldReceive('hasUndispatchedForIssue')->with(50)->once()->andReturn(true);
        $issue->shouldNotReceive('markDispatched');

        $result = $this->service->dispatch($issue, $this->plan(['created' => 1, 'deferred' => 1]));

        $this->assertSame(1, $result['deferred']);
    }

    public function test_dispatches_only_ids_already_claimed_by_planner(): void
    {
        $issue = $this->makeIssue();
        $this->repository->shouldNotReceive('claimForDispatch');
        $this->repository->shouldNotReceive('markDispatched');
        $this->repository->shouldReceive('hasUndispatchedForIssue')->with(50)->once()->andReturn(false);
        $issue->shouldReceive('markDispatched')->once();

        $result = $this->service->dispatch($issue, $this->plan([
            'digital_ids' => [10],
            'print_ids' => [11],
        ]));

        $this->assertSame(1, $result['digital_dispatches']);
        $this->assertSame(1, $result['print_dispatches']);
    }

    public function test_reports_each_non_dispatch_reason_separately(): void
    {
        $issue = $this->makeIssue();
        $this->repository->shouldReceive('hasUndispatchedForIssue')->with(50)->once()->andReturn(true);
        $issue->shouldNotReceive('markDispatched');

        $result = $this->service->dispatch($issue, $this->plan([
            'deferred' => 1,
            'not_due' => 2,
            'already_dispatched' => 3,
            'non_dispatchable_status' => 4,
            'claim_conflicts' => 5,
        ]));

        $this->assertSame(1, $result['deferred']);
        $this->assertSame(2, $result['not_due']);
        $this->assertSame(3, $result['already_dispatched']);
        $this->assertSame(4, $result['non_dispatchable_status']);
        $this->assertSame(5, $result['claim_conflicts']);
    }

    private function makeIssue(): IssueDelivery
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 50;
        return $issue;
    }

    private function plan(array $overrides = []): array
    {
        return array_merge([
            'digital_ids' => [],
            'print_ids' => [],
            'created' => 0,
            'deferred' => 0,
            'not_due' => 0,
            'already_dispatched' => 0,
            'non_dispatchable_status' => 0,
            'claim_conflicts' => 0,
        ], $overrides);
    }
}
