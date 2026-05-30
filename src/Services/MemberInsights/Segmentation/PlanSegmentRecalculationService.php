<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Repositories\MemberInsights\PlanSegmentRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Processes full plan-level re-evaluation of subscription segments.
 *
 * Used by:
 *   - The artisan command: `segments:recalculate`
 *   - The admin API: POST /api/subscription-plans/{id}/segments/recalculate
 *
 * After all subscriptions for a plan are processed, every segment that was
 * active on that plan has its `last_recalculated_at` stamped so the admin UI
 * can show when the segment was last evaluated.
 */
class PlanSegmentRecalculationService
{
    private const CHUNK_SIZE = 200;

    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly SegmentAssignmentService   $assignmentService,
        private readonly PlanSegmentRepository      $planSegmentRepository,
    ) {
    }

    /**
     * Recalculate all active subscriptions for a plan, then stamp
     * `last_recalculated_at` on every segment active on that plan.
     *
     * @return int Number of subscriptions processed.
     * @throws \InvalidArgumentException if the plan is not found.
     */
    public function recalculatePlan(int $planId): int
    {
        $plan = $this->planRepository->find($planId);

        if ($plan === null) {
            throw new \InvalidArgumentException("Plan #{$planId} not found.");
        }

        $processed = 0;

        $this->subscriptionRepository->chunkActiveByPlan(
            $planId,
            self::CHUNK_SIZE,
            function ($subscriptions) use (&$processed) {
                foreach ($subscriptions as $subscription) {
                    $this->assignmentService->assignForSubscription($subscription);
                    $processed++;
                }
            }
        );

        $this->stampRecalculatedAt($planId);

        return $processed;
    }

    // -------------------------------------------------------------------------

    /**
     * Stamp `last_recalculated_at = now()` on every segment currently active
     * on the plan. This covers all active plan-segment assignments regardless
     * of whether any subscriptions actually matched, which is correct — the
     * recalculation ran and produced a result (even if that result was zero
     * matches).
     */
    private function stampRecalculatedAt(int $planId): void
    {
        $now         = now_datetime();
        $assignments = $this->planSegmentRepository->getActiveForPlan($planId);

        foreach ($assignments as $assignment) {
            $segment = $assignment->segment;

            if ($segment === null) {
                continue;
            }

            $segment->last_recalculated_at = $now;
            $segment->save();
        }
    }
}