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
    public function test_marks_issue_complete_when_no_fulfilments_remain(): void
    {
        $repository = Mockery::mock(IssuesDeliveredRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 50;

        $repository->shouldReceive('hasUndispatchedForIssue')->with(50)->once()->andReturn(false);
        $issue->shouldReceive('markDispatched')->once();

        $service = new IssueFulfilmentDispatchCoordinator($repository, $logger);
        $result = $service->dispatch($issue, [
            'digital_ids' => [],
            'print_ids' => [],
            'created' => 0,
            'deferred' => 0,
        ]);

        $this->assertEquals(0, $result['digital_dispatches']);
        $this->assertEquals(0, $result['print_dispatches']);
    }

    public function test_keeps_issue_open_while_deferred_fulfilments_remain(): void
    {
        $repository = Mockery::mock(IssuesDeliveredRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 50;

        $repository->shouldReceive('hasUndispatchedForIssue')->with(50)->once()->andReturn(true);
        $issue->shouldNotReceive('markDispatched');

        $service = new IssueFulfilmentDispatchCoordinator($repository, $logger);
        $result = $service->dispatch($issue, [
            'digital_ids' => [],
            'print_ids' => [],
            'created' => 1,
            'deferred' => 1,
        ]);

        $this->assertEquals(1, $result['deferred']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
