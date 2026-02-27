<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionPricingChangeStatus;
use App\Events\Subscriptions\SubscriptionPricingChangeScheduled;
use App\Framework\Database\Database;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPricingChange;
use App\Repositories\Subscriptions\SubscriptionPricingChangeRepository;

/**
 * Orchestrates the lifecycle of a subscription price change.
 *
 * Rules enforced here:
 *  - Effective date must be at least MIN_NOTICE_DAYS from now (30 days, per policy).
 *  - A plan may not have more than one active (scheduled/notified) change at a time.
 *  - Applying a price updates the plan's price column inside a transaction.
 *  - Cancellation is only possible before the change is applied.
 *
 * This service does NOT format data for presentation and does NOT access
 * sessions, request globals, or HTTP context.
 */
class SubscriptionPricingChangeService
{
    public const MIN_NOTICE_DAYS = 30;

    public function __construct(
        private readonly SubscriptionPricingChangeRepository $repository,
        private readonly Database                            $database
    )
    {
    }

    /**
     * Schedule a price change for a plan.
     *
     * @throws \InvalidArgumentException if the effective date is too soon or another active change exists.
     */
    public function schedule(
        SubscriptionPlan   $plan,
        float              $newPrice,
        \DateTimeInterface $effectiveDate,
        int                $createdBy,
        ?string            $reason = null,
    ): SubscriptionPricingChange
    {
        $this->assertValidEffectiveDate($effectiveDate);
        $this->assertNoActiveChange($plan->id);

        return $this->database->transaction(function () use ($plan, $newPrice, $effectiveDate, $createdBy, $reason): SubscriptionPricingChange {
            $change = $this->repository->create([
                'plan_id' => $plan->id,
                'old_price' => $plan->price,
                'new_price' => $newPrice,
                'currency' => $plan->currency ?? 'GBP',
                'effective_date' => $effectiveDate->format('Y-m-d H:i:s'),
                'status' => SubscriptionPricingChangeStatus::Scheduled->value,
                'reason' => $reason,
                'created_by' => $createdBy,
            ]);

            event(new SubscriptionPricingChangeScheduled($change));

            return $change;
        });
    }

    /**
     * Apply a price change that has passed its effective date.
     *
     * Called by a scheduled command, not directly from HTTP context.
     *
     * @throws \RuntimeException if the change is not in a state eligible to apply.
     */
    public function apply(SubscriptionPricingChange $change): void
    {
        if (!$change->isDueToApply()) {
            throw new \RuntimeException(
                "Pricing change #{$change->id} is not due to apply (status: {$change->status}, effective: {$change?->effective_date->format('Y-m-d')})"
            );
        }

        $plan = $change->plan(true)->first();

        if (!$plan) {
            throw new \RuntimeException("Plan not found for pricing change #{$change->id}");
        }

        $this->database->transaction(function () use ($change, $plan): void {
            $this->repository->applyPlanPrice($plan, $change->new_price);
            $this->repository->markApplied($change);
        });
    }

    /**
     * Cancel a scheduled or notified (but not yet applied) price change.
     *
     * @throws \RuntimeException if the change has already been applied.
     */
    public function cancel(SubscriptionPricingChange $change): void
    {
        if ($change->isApplied()) {
            throw new \RuntimeException(
                "Cannot cancel pricing change #{$change->id}: it has already been applied."
            );
        }

        if ($change->isCancelled()) {
            return; // idempotent
        }

        $this->repository->markCancelled($change);
    }

    // ── Assertions ────────────────────────────────────────────────────────

    private function assertValidEffectiveDate(\DateTimeInterface $effectiveDate): void
    {
        $minDate = (new \DateTime())->modify('+' . self::MIN_NOTICE_DAYS . ' days');

        if ($effectiveDate < $minDate) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Effective date must be at least %d days from today. Minimum allowed: %s.',
                    self::MIN_NOTICE_DAYS,
                    $minDate->format('Y-m-d'),
                )
            );
        }
    }

    private function assertNoActiveChange(int $planId): void
    {
        $existing = $this->repository->findActivePlanChange($planId);

        if ($existing !== null) {
            throw new \InvalidArgumentException(
                "Plan #{$planId} already has an active pricing change (#{$existing->id}, status: {$existing->status}). Cancel it before scheduling a new one."
            );
        }
    }
}