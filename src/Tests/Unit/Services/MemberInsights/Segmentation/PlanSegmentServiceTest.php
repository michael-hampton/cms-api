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