<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Enums\Member\SegmentSubjectType;
use App\Models\PlanSegment;
use App\Repositories\MemberInsights\PlanSegmentRepository;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;

/**
 * Orchestrates the assignment and removal of segments to/from plans.
 *
 * Only segments with subject_type = 'plan' or 'subscription' may be assigned
 * to a plan. Member segments are rejected — they have no meaning in plan context.
 */
class PlanSegmentService
{
    public function __construct(
        private readonly PlanSegmentRepository $planSegmentRepository,
        private readonly SegmentRepository     $segmentRepository,
        private readonly SubscriptionPlanRepository        $planRepository,
    ) {
    }

    /**
     * Assign a segment to a plan.
     *
     * @throws \InvalidArgumentException if plan/segment not found, already assigned,
     *                                   or segment subject_type is 'member'.
     */
    public function assign(int $planId, int $segmentId, array $options = []): PlanSegment
    {
        $plan = $this->planRepository->find($planId);
        if ($plan === null) {
            throw new \InvalidArgumentException("Plan #{$planId} not found.");
        }

        $segment = $this->segmentRepository->findWithRules($segmentId);
        if ($segment === null) {
            throw new \InvalidArgumentException("Segment #{$segmentId} not found.");
        }

        if ($segment->subject_type === SegmentSubjectType::Member->value) {
            throw new \InvalidArgumentException(
                "Segment \"{$segment->key}\" is a member segment and cannot be assigned to a plan."
            );
        }

        echo $planId . ' - ' . $segmentId;

        $existing = $this->planSegmentRepository->findByPlanAndSegment($planId, $segmentId);
        if ($existing !== null) {
            throw new \InvalidArgumentException(
                "Segment \"{$segment->key}\" is already assigned to plan #{$planId}."
            );
        }

        return $this->planSegmentRepository->assign($planId, $segmentId, $options);
    }

    /**
     * Remove a segment assignment from a plan.
     *
     * @throws \InvalidArgumentException if no assignment exists.
     */
    public function remove(int $planId, int $segmentId): void
    {
        $existing = $this->planSegmentRepository->findByPlanAndSegment($planId, $segmentId);
        if ($existing === null) {
            throw new \InvalidArgumentException(
                "Segment #{$segmentId} is not assigned to plan #{$planId}."
            );
        }

        $this->planSegmentRepository->removeByPlanAndSegment($planId, $segmentId);
    }
}