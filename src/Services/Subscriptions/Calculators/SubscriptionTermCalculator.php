<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Calculators;

use App\Models\Subscription;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Pure calculations describing where a subscription sits in its term —
 * age, remaining days, and pause usage. Used to build
 * CancellationPolicyContext / PausePolicyContext without putting
 * calculation logic inside the orchestrating services (per contract:
 * "calculations and decisions live in dedicated collaborators").
 *
 * No repository access, no persistence, no side effects — everything it
 * needs is read off the Subscription model already loaded by the caller.
 */
class SubscriptionTermCalculator
{
    public function ageDays(Subscription $subscription, ?DateTimeImmutable $now = null): int
    {
        $startDate = $subscription->start_date;

        if (!$startDate instanceof DateTimeInterface) {
            return 0;
        }

        $now ??= new DateTimeImmutable();

        if ($startDate > $now) {
            return 0;
        }

        return $now->diff($startDate)->days;
    }

    /**
     * Days remaining until end_date, or null when the subscription has
     * no end_date (e.g. lifetime plans) — distinct from 0, which means
     * the term has already ended.
     */
    public function remainingTermDays(Subscription $subscription, ?DateTimeImmutable $now = null): ?int
    {
        $endDate = $subscription->end_date;

        if (!$endDate instanceof DateTimeInterface) {
            return null;
        }

        $now ??= new DateTimeImmutable();

        if ($endDate < $now) {
            return 0;
        }

        return $now->diff($endDate)->days;
    }

    /**
     * How many pauses this subscription has used within its current
     * term/billing period.
     *
     * ASSUMPTION / KNOWN LIMITATION: the schema has no pause-history table
     * or persisted counter — subscriptions only carry the *current*
     * pause's fields (paused_at, resumed_at, pause_until). This method can
     * therefore only detect "has this subscription been paused-and-resumed
     * at least once since the current period started" (returns 1) versus
     * "never" (returns 0); it cannot distinguish one prior pause from
     * several. That's sufficient for a "one pause per term" policy rule
     * (StandardConsumerPolicy) to correctly block a second pause attempt,
     * but is not a general-purpose usage counter. A real count would need
     * a migration adding a persisted counter or a pause-history log —
     * flagging as a follow-up rather than adding schema changes
     * unilaterally here.
     */
    public function pausesUsedThisTerm(Subscription $subscription, ?DateTimeImmutable $now = null): int
    {
        $resumedAt = $subscription->resumed_at;

        if (!$resumedAt instanceof DateTimeInterface) {
            return 0;
        }

        $periodStart = $subscription->current_period_start instanceof DateTimeInterface
            ? $subscription->current_period_start
            : $subscription->start_date;

        if (!$periodStart instanceof DateTimeInterface) {
            return 1;
        }

        return $resumedAt >= $periodStart ? 1 : 0;
    }
}
