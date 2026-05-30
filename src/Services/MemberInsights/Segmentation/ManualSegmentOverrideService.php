<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Framework\Database\Database;
use App\Models\SubscriptionSegment;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Repositories\MemberInsights\SubscriptionSegmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Handles manual CRM/admin overrides of a subscription's segment assignment.
 *
 * A manual override:
 *   - Requires a reason (not empty).
 *   - Replaces any current active assignment (rule-based or prior manual).
 *   - Stores source = 'manual', reason, optional expires_at, and the acting user.
 *   - An expired manual override no longer blocks rule-based re-assignment.
 *
 * The SegmentAssignmentService (batch/webhook path) should check
 * hasActiveManualOverride() before overwriting — this service does not enforce
 * that guard itself, keeping both services independent.
 */
class ManualSegmentOverrideService
{
    public function __construct(
        private readonly SubscriptionRepository        $subscriptionRepository,
        private readonly SegmentRepository             $segmentRepository,
        private readonly SubscriptionSegmentRepository $subscriptionSegmentRepository,
        private readonly Database                      $database,
    ) {
    }

    /**
     * @throws \InvalidArgumentException if subscription/segment not found, reason empty,
     *                                   or expires_at is in the past.
     */
    public function override(
        int     $subscriptionId,
        int     $segmentId,
        string  $reason,
        ?int    $assignedByUserId,
        ?string $expiresAt = null,
    ): SubscriptionSegment {
        $subscription = $this->subscriptionRepository->find($subscriptionId);
        if ($subscription === null) {
            throw new \InvalidArgumentException("Subscription #{$subscriptionId} not found.");
        }

        $segment = $this->segmentRepository->findWithRules($segmentId);
        if ($segment === null) {
            throw new \InvalidArgumentException("Segment #{$segmentId} not found.");
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required for manual segment overrides.');
        }

        $expiresAtDate = null;
        if ($expiresAt !== null) {
            try {
                $expiresAtDate = new \DateTimeImmutable($expiresAt);
            } catch (\Exception) {
                throw new \InvalidArgumentException("expires_at \"{$expiresAt}\" is not a valid date.");
            }

            if ($expiresAtDate <= now_datetime()) {
                throw new \InvalidArgumentException('expires_at must be a future date.');
            }
        }

        return $this->database->transaction(
            function () use ($subscriptionId, $segmentId, $reason, $assignedByUserId, $expiresAtDate) {
                $this->subscriptionSegmentRepository->replaceActive($subscriptionId);

                return $this->subscriptionSegmentRepository->createManual(
                    subscriptionId:     $subscriptionId,
                    segmentId:          $segmentId,
                    assignedAt:         now_datetime(),
                    reason:             $reason,
                    assignedByUserId:   $assignedByUserId,
                    expiresAt:          $expiresAtDate,
                );
            }
        );
    }
}