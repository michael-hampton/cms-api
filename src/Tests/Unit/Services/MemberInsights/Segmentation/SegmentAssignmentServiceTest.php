<?php

namespace App\Tests\Unit\Services\MemberInsights\Segmentation;

use App\Events\Subscriptions\SubscriptionSegmentAssigned;
use App\Framework\Database\Database;
use App\Models\PlanSegment;
use App\Models\Segment;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Repositories\MemberInsights\PlanSegmentRepository;
use App\Repositories\MemberInsights\SubscriptionSegmentRepository;
use App\Services\MemberInsights\Segmentation\SegmentAssignmentService;
use App\Services\MemberInsights\Segmentation\SegmentRuleEngine;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SegmentAssignmentServiceTest extends TestCase
{
    private SegmentRuleEngine|MockInterface $ruleEngine;
    private PlanSegmentRepository|MockInterface $planSegmentRepository;
    private SubscriptionSegmentRepository|MockInterface $subscriptionSegmentRepository;
    private Database|MockInterface $database;
    private CapturingEventDispatcher $events;
    private SegmentAssignmentService $service;

    // =========================================================================
    // Priority waterfall
    // =========================================================================

    public function test_it_assigns_first_matching_segment(): void
    {
        $subscription   = $this->makeSubscription(1, planId: 10);
        $segment        = $this->makeSegment(5);
        $planSegment    = $this->makePlanSegment($segment, priority: 10);
        $assignment     = Mockery::mock(SubscriptionSegment::class)->makePartial();

        $this->planSegmentRepository->allows('getActiveForPlan')->with(10)->andReturn(collect([$planSegment]));
        $this->ruleEngine->allows('matches')->with($subscription, $segment)->andReturn(true);
        $this->database->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->subscriptionSegmentRepository->expects('replaceActive')->once()->with(1);
        $this->subscriptionSegmentRepository->expects('createSubscriptionSegment')
            ->once()
            ->with(1, 5, Mockery::type(\DateTimeInterface::class))
            ->andReturn($assignment);

        $result = $this->service->assignForSubscription($subscription);

        $this->assertSame($assignment, $result);
        $this->events->assertDispatched(
            SubscriptionSegmentAssigned::class,
            fn(SubscriptionSegmentAssigned $event): bool => $event->subscriptionSegment === $assignment
        );
    }

    public function test_it_respects_priority_and_skips_non_matching_segment(): void
    {
        $subscription    = $this->makeSubscription(1, planId: 10);
        $segment1        = $this->makeSegment(5);
        $segment2        = $this->makeSegment(6);
        $planSegment1    = $this->makePlanSegment($segment1, priority: 10);
        $planSegment2    = $this->makePlanSegment($segment2, priority: 20);
        $assignment      = Mockery::mock(SubscriptionSegment::class)->makePartial();

        $this->planSegmentRepository->allows('getActiveForPlan')
            ->andReturn(collect([$planSegment1, $planSegment2]));

        // Segment 1 (priority 10) does NOT match; segment 2 (priority 20) DOES.
        $this->ruleEngine->allows('matches')
            ->with($subscription, $segment1)->andReturn(false);
        $this->ruleEngine->allows('matches')
            ->with($subscription, $segment2)->andReturn(true);

        $this->database->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->subscriptionSegmentRepository->allows('replaceActive');
        $this->subscriptionSegmentRepository->allows('createSubscriptionSegment')
            ->with(1, 6, Mockery::any())
            ->andReturn($assignment);

        $result = $this->service->assignForSubscription($subscription);

        $this->assertSame($assignment, $result);
    }

    public function test_it_returns_null_when_no_segment_matches(): void
    {
        $subscription = $this->makeSubscription(1, planId: 10);
        $segment      = $this->makeSegment(5);
        $planSegment  = $this->makePlanSegment($segment, priority: 10);

        $this->planSegmentRepository->allows('getActiveForPlan')->andReturn(collect([$planSegment]));
        $this->ruleEngine->allows('matches')->andReturn(false);

        $this->subscriptionSegmentRepository->shouldNotReceive('replaceActive');
        $this->subscriptionSegmentRepository->shouldNotReceive('createSubscriptionSegment');

        $result = $this->service->assignForSubscription($subscription);

        $this->assertNull($result);
    }

    // =========================================================================
    // Assignment replaces previous
    // =========================================================================

    public function test_it_replaces_previous_active_assignment(): void
    {
        $subscription = $this->makeSubscription(1, planId: 10);
        $segment      = $this->makeSegment(5);
        $planSegment  = $this->makePlanSegment($segment, priority: 10);
        $newAssignment = Mockery::mock(SubscriptionSegment::class)->makePartial();

        $this->planSegmentRepository->allows('getActiveForPlan')->andReturn(collect([$planSegment]));
        $this->ruleEngine->allows('matches')->andReturn(true);
        $this->database->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        // replaceActive MUST be called before create
        $this->subscriptionSegmentRepository->expects('replaceActive')
            ->once()
            ->with(1)
            ->ordered();

        $this->subscriptionSegmentRepository->expects('createSubscriptionSegment')
            ->once()
            ->ordered()
            ->andReturn($newAssignment);

        $this->service->assignForSubscription($subscription);

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // No plan
    // =========================================================================

    public function test_it_returns_null_when_subscription_has_no_plan(): void
    {
        $subscription = $this->makeSubscription(1, planId: null);

        $this->planSegmentRepository->shouldNotReceive('getActiveForPlan');

        $result = $this->service->assignForSubscription($subscription);

        $this->assertNull($result);
    }

    // =========================================================================
    // Transaction usage
    // =========================================================================

    public function test_it_wraps_assignment_in_a_transaction(): void
    {
        $subscription = $this->makeSubscription(1, planId: 10);
        $segment      = $this->makeSegment(5);
        $planSegment  = $this->makePlanSegment($segment, priority: 10);
        $assignment   = Mockery::mock(SubscriptionSegment::class)->makePartial();

        $this->planSegmentRepository->allows('getActiveForPlan')->andReturn(collect([$planSegment]));
        $this->ruleEngine->allows('matches')->andReturn(true);

        $this->database->expects('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->subscriptionSegmentRepository->allows('replaceActive');
        $this->subscriptionSegmentRepository->allows('createSubscriptionSegment')->andReturn($assignment);

        $this->service->assignForSubscription($subscription);

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSubscription(int $id, ?int $planId): Subscription
    {
        $sub          = Mockery::mock(Subscription::class)->makePartial();
        $sub->id      = $id;
        $sub->plan_id = $planId;

        return $sub;
    }

    private function makeSegment(int $id): Segment
    {
        $segment     = Mockery::mock(Segment::class)->makePartial();
        $segment->id = $id;

        return $segment;
    }

    private function makePlanSegment(Segment $segment, int $priority): PlanSegment
    {
        $ps           = Mockery::mock(PlanSegment::class)->makePartial();
        $ps->priority = $priority;
        $ps->segment  = $segment;

        return $ps;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ruleEngine                    = Mockery::mock(SegmentRuleEngine::class);
        $this->planSegmentRepository         = Mockery::mock(PlanSegmentRepository::class);
        $this->subscriptionSegmentRepository = Mockery::mock(SubscriptionSegmentRepository::class);
        $this->database                      = Mockery::mock(Database::class);
        $this->events                        = CapturingEventDispatcher::fake();
        $this->service                       = new SegmentAssignmentService(
            $this->ruleEngine,
            $this->planSegmentRepository,
            $this->subscriptionSegmentRepository,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
