<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

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
 *
 * $reason: was the SubscriptionCancellationReason enum; now the
 * DB-driven CancellationReason's `code` (see CancellationReasonSeeder for
 * the legacy values preserved as seeded rows). No policy implementation
 * pattern-matches on specific reason values today — this remains a
 * pass-through field for logging/audit — so a plain string is sufficient
 * and avoids coupling this DTO to a persisted model.
 *
 * $settingOverrides: see PausePolicyContext for why this is pre-populated
 * rather than looked up inside evaluateCancellation() itself.
 */
final class CancellationPolicyContext
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $planId,
        public readonly ?string $reason,
        public readonly ?string $cancellationNotes,
        public readonly ?DateTimeImmutable $requestedCancellationDate,
        public readonly SubscriptionStatus $currentStatus,
        public readonly int $subscriptionAgeDays,
        public readonly ?int $remainingTermDays,
        public readonly SubscriptionPolicySettingOverrides $settingOverrides = new SubscriptionPolicySettingOverrides(),
    ) {
    }
}