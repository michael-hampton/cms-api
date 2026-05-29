<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Repositories\MemberInsights\PlanSegmentRepository;
use App\Repositories\MemberInsights\SubscriptionSegmentRepository;

/**
 * Central assignment service for subscription segment evaluation.
 *
 * Used by both batch processing (Ticket 8) and real-time webhook handlers (Ticket 9).
 *
 * Algorithm:
 *   1. Load all active plan-segment assignments for the subscription's plan,
 *      ordered by priority ascending (lowest number = highest priority).
 *   2. Evaluate each segment's rules against the subscription using SegmentRuleEngine.
 *   3. The first segment that matches is assigned.
 *   4. If a previous active assignment exists it is marked 'replaced'.
 *   5. If no segment matches, the subscription is left unassigned (no write occurs).
 *
 * This is wrapped in a transaction: replace + create are atomic.
 */
class SegmentAssignmentService
{
    public function __construct(
        private readonly SegmentRuleEngine               $ruleEngine,
        private readonly PlanSegmentRepository           $planSegmentRepository,
        private readonly SubscriptionSegmentRepository   $subscriptionSegmentRepository,
        private readonly Database                        $database,
    ) {
    }

    /**
     * Evaluate and assign the highest-priority matching segment for the subscription.
     *
     * Returns the new SubscriptionSegment if a match was found, or null.
     */
    public function assignForSubscription(Subscription $subscription): ?SubscriptionSegment
    {
        $planId = $subscription->plan_id;

        if ($planId === null) {
            return null;
        }

        $planSegments = $this->planSegmentRepository->getActiveForPlan($planId);

        foreach ($planSegments as $planSegment) {
            $segment = $planSegment->segment;

            if ($segment === null) {
                continue;
            }

            if (!$this->ruleEngine->matches($subscription, $segment)) {
                continue;
            }

            // First match found — assign inside a transaction.
            return $this->database->transaction(function () use ($subscription, $segment) {
                $this->subscriptionSegmentRepository->replaceActive($subscription->id);

                return $this->subscriptionSegmentRepository->createSubscriptionSegment(
                    $subscription->id,
                    $segment->id,
                    now_datetime(),
                );
            });
        }

        return null;
    }
}