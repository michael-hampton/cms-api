<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\SubscriptionCancellationReason;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Subscription;
use DateTimeImmutable;

/**
 * Everything a ReplacementPolicyInterface::evaluateCancellation() call
 * needs to decide whether a cancellation is permitted, so policies never
 * query repositories directly (same principle as PolicyContext for
 * replace/extend).
 *
 * ASSUMPTION: unlike PolicyContext (replace/extend), this deliberately
 * does not carry a resolved SubscriptionPlan. The ticket's field list for
 * cancellation context (reason, requested date, current status,
 * subscription age, remaining term) doesn't call for the plan object
 * itself, and per-plan behaviour is already achieved by
 * ReplacementPolicyResolver choosing *which* policy instance evaluates
 * this context via plan_id — no policy implementation needs the plan
 * relation loaded to make that decision. $planId is included for
 * logging/audit purposes only. Add the relation back if a future policy
 * needs plan-level configuration (e.g. billing_period) directly.
 */
final class CancellationPolicyContext
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $planId,
        public readonly ?SubscriptionCancellationReason $reason,
        public readonly ?string $cancellationNotes,
        public readonly ?DateTimeImmutable $requestedCancellationDate,
        public readonly SubscriptionStatus $currentStatus,
        public readonly int $subscriptionAgeDays,
        public readonly ?int $remainingTermDays,
    ) {
    }
}
