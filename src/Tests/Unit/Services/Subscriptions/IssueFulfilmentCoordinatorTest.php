<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Events\Subscriptions\IssueDeliveryDispatched;
use App\Framework\Container;
use App\Framework\Events\EventDispatcher;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\Job;
use App\Framework\Queue\QueueDriverInterface;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\DeliverIssueDeliveryJob;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Services\Subscriptions\IssueFulfilmentDispatchCoordinator;
use App\Tests\Unit\UnitTestCase;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;

class IssueFulfilmentCoordinatorTest extends UnitTestCase
{
    private $repository;
    private $logger;
    private IssueFulfilmentDispatchCoordinator $service;
    private CapturingEventDispatcher $events;
    private array $queuedJobs = [];

    protected function setUp(): void
    {
        $this->repository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->service = new IssueFulfilmentDispatchCoordinator($this->repository, $this->logger);
        $this->events = CapturingEventDispatcher::fake();
        $queueDriver = Mockery::mock(QueueDriverInterface::class);

        $queueDriver->shouldReceive('push')->andReturnUsing(function (Job $job) {
            $this->queuedJobs[] = $job;

            return count($this->queuedJobs); // <--- Return an integer here
        });

        Container::getInstance()->instance(Dispatcher::class, new Dispatcher($queueDriver));
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

    public function test_dispatches_digital_jobs_for_claimed_ids_without_reclaiming(): void
    {
        $issue = $this->makeIssue();
        $this->repository->shouldNotReceive('claimForDispatch');
        $this->repository->shouldNotReceive('markDispatched');
        $this->repository->shouldReceive('hasUndispatchedForIssue')->with(50)->once()->andReturn(false);
        $issue->shouldReceive('markDispatched')->once();

        $result = $this->service->dispatch($issue, $this->plan(['digital_ids' => [10, 11]]));

        $digitalJobs = array_values(array_filter($this->queuedJobs, function ($job) {
            return $job instanceof DeliverIssueDeliveryJob;
        }));
        $this->assertCount(2, $digitalJobs);
        $this->assertSame(2, $result['digital_dispatches']);
    }

    public function test_releases_only_unhanded_claims_when_a_digital_queue_push_fails(): void
    {
        $issue = $this->makeIssue();
        $pushCount = 0;
        $queueDriver = Mockery::mock(QueueDriverInterface::class);
        $queueDriver->shouldReceive('push')->andReturnUsing(function () use (&$pushCount) {
            $pushCount++;

            if ($pushCount === 2) {
                throw new \RuntimeException('Queue unavailable');
            }

            return $pushCount; // <--- Return an integer here
        });
        Container::getInstance()->instance(Dispatcher::class, new Dispatcher($queueDriver));

        $this->repository->shouldReceive('releaseDispatchClaims')
            ->once()
            ->with([11, 12, 20, 21])
            ->andReturn(4);
        $this->repository->shouldNotReceive('hasUndispatchedForIssue');
        $issue->shouldNotReceive('markDispatched');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Queue unavailable');

        $this->service->dispatch($issue, $this->plan([
            'digital_ids' => [10, 11, 12],
            'print_ids' => [20, 21],
        ]));
    }

    public function test_emits_print_event_after_planner_has_claimed_rows(): void
    {
        $issue = $this->makeIssue();
        $this->repository->shouldNotReceive('claimForDispatch');
        $this->repository->shouldNotReceive('markDispatched');
        $this->repository->shouldReceive('hasUndispatchedForIssue')->with(50)->once()->andReturn(false);
        $issue->shouldReceive('markDispatched')->once();

        $this->service->dispatch($issue, $this->plan([
            'print_ids' => [20, 21],
            'created' => 2,
            'deferred' => 1,
            'not_due' => 2,
            'claim_conflicts' => 1,
        ]));

        $this->events->assertDispatched(IssueDeliveryDispatched::class, function ($event) {
            return $event->eligibleCount === 2
                && $event->createdCount === 2
                && $event->skippedCount === 4;
        });
    }

    public function test_releases_print_claims_when_event_handoff_fails(): void
    {
        $issue = $this->makeIssue();
        $dispatcher = new class extends EventDispatcher {
            public function dispatch(object $event): void
            {
                throw new \RuntimeException('Event handoff failed');
            }
        };
        Container::getInstance()->instance(EventDispatcher::class, $dispatcher);

        $this->repository->shouldReceive('releaseDispatchClaims')
            ->once()
            ->with([20, 21])
            ->andReturn(2);
        $this->repository->shouldNotReceive('hasUndispatchedForIssue');
        $issue->shouldNotReceive('markDispatched');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Event handoff failed');

        $this->service->dispatch($issue, $this->plan(['print_ids' => [20, 21]]));
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
