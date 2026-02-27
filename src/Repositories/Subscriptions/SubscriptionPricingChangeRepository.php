<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\SubscriptionPricingChangeStatus;
use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPricingChange;

/**
 * Persistence-only. No business logic lives here.
 */
class SubscriptionPricingChangeRepository
{
    public function create(array $attributes): Model
    {
        return SubscriptionPricingChange::create($attributes);
    }

    public function findById(int $id): ?Model
    {
        return SubscriptionPricingChange::find($id);
    }

    /**
     * The single active (scheduled or notified) change for a plan, if any.
     */
    public function findActivePlanChange(int $planId): ?SubscriptionPricingChange
    {
        return SubscriptionPricingChange::where('plan_id', $planId)
            ->whereIn('status', [
                SubscriptionPricingChangeStatus::Scheduled->value,
                SubscriptionPricingChangeStatus::Notified->value,
            ])
            ->first();
    }

    /**
     * Changes that have been scheduled but no notice emails sent yet.
     */
    public function findPendingNotification(): array
    {
        return SubscriptionPricingChange::where('status', SubscriptionPricingChangeStatus::Scheduled->value)
            ->whereNull('notice_sent_at')
            ->get()
            ->all();
    }

    /**
     * Changes that have been notified and whose effective_date has passed.
     */
    public function findDueToApply(): array
    {
        return SubscriptionPricingChange::where('status', SubscriptionPricingChangeStatus::Notified->value)
            ->where('effective_date', '<=', now())
            ->get()
            ->all();
    }

    /**
     * All active subscriptions on the given plan that should receive a notice.
     * Excludes cancelled and expired subscriptions.
     */
    public function findActiveSubscribersForPlan(int $planId): array
    {
        return Subscription::where('plan_id', $planId)
            ->whereIn('status', Subscription::ACTIVE_STATUSES)
            ->with(['member'])
            ->get()
            ->all();
    }

    public function markNotified(SubscriptionPricingChange $change): void
    {
        $change->status = SubscriptionPricingChangeStatus::Notified->value;
        $change->notice_sent_at = now();
        $change->save();
    }

    public function markApplied(SubscriptionPricingChange $change): void
    {
        $change->status = SubscriptionPricingChangeStatus::Applied->value;
        $change->save();
    }

    public function markCancelled(SubscriptionPricingChange $change): void
    {
        $change->status = SubscriptionPricingChangeStatus::Cancelled->value;
        $change->save();
    }

    /**
     * Update the plan's price column to reflect the new price.
     */
    public function applyPlanPrice(SubscriptionPlan $plan, float $newPrice): void
    {
        $plan->price = $newPrice;
        $plan->save();
    }
}