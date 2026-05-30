<?php

namespace App\Tests\Unit\Services\MemberInsights\Segmentation;

use App\Enums\Member\SegmentSubjectType;
use App\Models\PlanSegment;
use App\Models\Segment;
use App\Models\SubscriptionPlan;
use App\Repositories\MemberInsights\PlanSegmentRepository;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\MemberInsights\Segmentation\PlanSegmentService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PlanSegmentServiceTest extends TestCase
{
    private PlanSegmentRepository|MockInterface $planSegmentRepository;
    private SegmentRepository|MockInterface $segmentRepository;
    private SubscriptionPlanRepository|MockInterface $planRepository;
    private PlanSegmentService $service;

    // -------------------------------------------------------------------------
    // assign() — single plan
    // -------------------------------------------------------------------------

    public function test_it_assigns_segment_to_plan(): void
    {
        $plan       = $this->makePlan(1);
        $segment    = $this->makeSegment(10, SegmentSubjectType::Subscription);
        $assignment = Mockery::mock(PlanSegment::class)->makePartial();

        $this->planRepository->allows('find')->with(1)->andReturn($plan);
        $this->segmentRepository->allows('findWithRules')->with(10)->andReturn($segment);
        $this->planSegmentRepository->allows('findByPlanAndSegment')->with(1, 10)->andReturnNull();
        $this->planSegmentRepository->expects('assign')
            ->once()
            ->with(1, 10, [])
            ->andReturn($assignment);

        $result = $this->service->assign(1, 10);

        $this->assertSame($assignment, $result);
    }

    public function test_it_removes_segment_from_plan(): void
    {
        $assignment = Mockery::mock(PlanSegment::class)->makePartial();

        $this->planSegmentRepository->allows('findByPlanAndSegment')->with(1, 10)->andReturn($assignment);
        $this->planSegmentRepository->expects('removeByPlanAndSegment')->once()->with(1, 10);

        $this->service->remove(1, 10);

        $this->addToAssertionCount(1);
    }

    public function test_assign_throws_when_plan_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Plan #99 not found/');

        $this->planRepository->allows('find')->with(99)->andReturnNull();

        $this->service->assign(99, 10);
    }

    public function test_assign_throws_when_segment_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Segment #99 not found/');

        $plan = $this->makePlan(1);
        $this->planRepository->allows('find')->andReturn($plan);
        $this->segmentRepository->allows('findWithRules')->with(99)->andReturnNull();

        $this->service->assign(1, 99);
    }

    public function test_assign_throws_when_member_segment_assigned_to_plan(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/member segment/');

        $plan    = $this->makePlan(1);
        $segment = $this->makeSegment(5, SegmentSubjectType::Member);

        $this->planRepository->allows('find')->andReturn($plan);
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);

        $this->service->assign(1, 5);
    }

    public function test_assign_throws_on_duplicate_assignment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already assigned/');

        $plan       = $this->makePlan(1);
        $segment    = $this->makeSegment(10, SegmentSubjectType::Subscription);
        $existing   = Mockery::mock(PlanSegment::class)->makePartial();

        $this->planRepository->allows('find')->andReturn($plan);
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);
        $this->planSegmentRepository->allows('findByPlanAndSegment')->andReturn($existing);

        $this->service->assign(1, 10);
    }

    public function test_remove_throws_when_assignment_does_not_exist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not assigned/');

        $this->planSegmentRepository->allows('findByPlanAndSegment')->andReturnNull();

        $this->service->remove(1, 10);
    }

    // -------------------------------------------------------------------------
    // assignPlans() — bulk
    // -------------------------------------------------------------------------

    public function test_assign_plans_assigns_all_new_plans(): void
    {
        $segment     = $this->makeSegment(10, SegmentSubjectType::Subscription);
        $plan1       = $this->makePlan(1);
        $plan2       = $this->makePlan(2);
        $assignment1 = Mockery::mock(PlanSegment::class)->makePartial();
        $assignment2 = Mockery::mock(PlanSegment::class)->makePartial();

        $this->segmentRepository->allows('findWithRules')->with(10)->andReturn($segment);
        $this->planRepository->allows('find')->with(1)->andReturn($plan1);
        $this->planRepository->allows('find')->with(2)->andReturn($plan2);
        $this->planSegmentRepository->allows('findByPlanAndSegment')->andReturnNull();
        $this->planSegmentRepository->expects('assign')->with(1, 10, [])->andReturn($assignment1);
        $this->planSegmentRepository->expects('assign')->with(2, 10, [])->andReturn($assignment2);

        $result = $this->service->assignPlans(10, [1, 2]);

        $this->assertCount(2, $result['assigned']);
        $this->assertCount(0, $result['skipped']);
        $this->assertSame($assignment1, $result['assigned'][0]);
        $this->assertSame($assignment2, $result['assigned'][1]);
    }

    public function test_assign_plans_skips_already_assigned_plans(): void
    {
        $segment    = $this->makeSegment(10, SegmentSubjectType::Subscription);
        $plan1      = $this->makePlan(1);
        $plan2      = $this->makePlan(2);
        $existing   = Mockery::mock(PlanSegment::class)->makePartial();
        $assignment = Mockery::mock(PlanSegment::class)->makePartial();

        $this->segmentRepository->allows('findWithRules')->andReturn($segment);
        $this->planRepository->allows('find')->with(1)->andReturn($plan1);
        $this->planRepository->allows('find')->with(2)->andReturn($plan2);
        // Plan 1 already assigned, plan 2 is new
        $this->planSegmentRepository->allows('findByPlanAndSegment')->with(1, 10)->andReturn($existing);
        $this->planSegmentRepository->allows('findByPlanAndSegment')->with(2, 10)->andReturnNull();
        $this->planSegmentRepository->expects('assign')->once()->with(2, 10, [])->andReturn($assignment);

        $result = $this->service->assignPlans(10, [1, 2]);

        $this->assertCount(1, $result['assigned']);
        $this->assertCount(1, $result['skipped']);
        $this->assertContains(1, $result['skipped']);
    }

    public function test_assign_plans_returns_empty_when_all_already_assigned(): void
    {
        $segment  = $this->makeSegment(10, SegmentSubjectType::Subscription);
        $plan     = $this->makePlan(1);
        $existing = Mockery::mock(PlanSegment::class)->makePartial();

        $this->segmentRepository->allows('findWithRules')->andReturn($segment);
        $this->planRepository->allows('find')->andReturn($plan);
        $this->planSegmentRepository->allows('findByPlanAndSegment')->andReturn($existing);
        $this->planSegmentRepository->expects('assign')->never();

        $result = $this->service->assignPlans(10, [1]);

        $this->assertCount(0, $result['assigned']);
        $this->assertCount(1, $result['skipped']);
    }

    public function test_assign_plans_throws_when_segment_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Segment #99 not found/');

        $this->segmentRepository->allows('findWithRules')->with(99)->andReturnNull();

        $this->service->assignPlans(99, [1, 2]);
    }

    public function test_assign_plans_throws_when_segment_is_member_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/member segment/');

        $segment = $this->makeSegment(5, SegmentSubjectType::Member);
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);

        $this->service->assignPlans(5, [1]);
    }

    public function test_assign_plans_throws_when_any_plan_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Plan #999 not found/');

        $segment = $this->makeSegment(10, SegmentSubjectType::Subscription);
        $plan1   = $this->makePlan(1);

        $this->segmentRepository->allows('findWithRules')->andReturn($segment);
        $this->planSegmentRepository->allows('findByPlanAndSegment')->andReturnNull();

        // 👇 ADD THIS LINE to allow the repository to handle the assignment of the first plan
        $this->planSegmentRepository->allows('assign')->andReturn(Mockery::mock(PlanSegment::class)->makePartial());

        $this->planRepository->allows('find')->with(1)->andReturn($plan1);
        $this->planRepository->allows('find')->with(999)->andReturnNull();

        $this->service->assignPlans(10, [1, 999]);
    }

    public function test_assign_plans_passes_options_to_repository(): void
    {
        $segment    = $this->makeSegment(10, SegmentSubjectType::Subscription);
        $plan       = $this->makePlan(1);
        $assignment = Mockery::mock(PlanSegment::class)->makePartial();
        $options    = ['priority' => 50, 'is_active' => true];

        $this->segmentRepository->allows('findWithRules')->andReturn($segment);
        $this->planRepository->allows('find')->andReturn($plan);
        $this->planSegmentRepository->allows('findByPlanAndSegment')->andReturnNull();
        $this->planSegmentRepository->expects('assign')
            ->once()
            ->with(1, 10, $options)
            ->andReturn($assignment);

        $this->service->assignPlans(10, [1], $options);

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePlan(int $id): SubscriptionPlan
    {
        $plan     = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = $id;

        return $plan;
    }

    private function makeSegment(int $id, SegmentSubjectType $subjectType): Segment
    {
        $segment               = Mockery::mock(Segment::class)->makePartial();
        $segment->id           = $id;
        $segment->key          = 'test_segment';
        $segment->subject_type = $subjectType->value;

        return $segment;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->planSegmentRepository = Mockery::mock(PlanSegmentRepository::class);
        $this->segmentRepository     = Mockery::mock(SegmentRepository::class);
        $this->planRepository        = Mockery::mock(SubscriptionPlanRepository::class);
        $this->service               = new PlanSegmentService(
            $this->planSegmentRepository,
            $this->segmentRepository,
            $this->planRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}