<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Processes full plan-level re-evaluation of subscription segments.
 *
 * Used by:
 *   - The artisan command: `segments:recalculate`
 *   - The admin API: POST /api/plans/{id}/segments/recalculate
 *
 * Chunking ensures large plans don't exhaust memory or hold long-lived DB connections.
 * The assignment logic is fully delegated to SegmentAssignmentService so there is
 * zero duplication between batch and real-time paths.
 */
class PlanSegmentRecalculationService
{
    private const CHUNK_SIZE = 200;

    public function __construct(
        private readonly SubscriptionPlanRepository              $planRepository,
        private readonly SubscriptionRepository      $subscriptionRepository,
        private readonly SegmentAssignmentService    $assignmentService,
    ) {
    }

    /**
     * Recalculate all active subscriptions for a plan.
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

        return $processed;
    }
}