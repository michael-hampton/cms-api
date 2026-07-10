<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Subscription;
use DateTimeImmutable;

/**
 * Everything a ReplacementPolicyInterface::evaluatePause() call needs to
 * decide whether a pause is permitted, so policies never query
 * repositories directly.
 *
 * See CancellationPolicyContext for why the resolved SubscriptionPlan
 * relation is intentionally omitted in favour of $planId.
 *
 * $pausesUsedThisTerm: see SubscriptionTermCalculator::pausesUsedThisTerm()
 * for the important caveat on how this is currently derived — there is no
 * persisted pause-history/count column in the schema yet, so this is a
 * best-effort signal (0 or 1), not an exact count.
 */
final class PausePolicyContext
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $planId,
        public readonly ?DateTimeImmutable $requestedPauseDate,
        public readonly ?DateTimeImmutable $requestedResumeDate,
        public readonly SubscriptionStatus $currentStatus,
        public readonly int $subscriptionAgeDays,
        public readonly ?int $remainingTermDays,
        public readonly int $pausesUsedThisTerm,
    ) {
    }
}
