<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\SubscriptionPricingChangeTransitionStatus;
use App\Models\Model;
use App\Models\SubscriptionPricingChangeTransition;
use App\Repositories\Repository;

class SubscriptionPricingChangeTransitionRepository extends Repository
{
    public function findForOldSubscription(
        int $pricingChangeId,
        int $oldSubscriptionId
    ): ?SubscriptionPricingChangeTransition {
        return SubscriptionPricingChangeTransition::where('subscription_pricing_change_id', $pricingChangeId)
            ->where('old_subscription_id', $oldSubscriptionId)
            ->first();
    }

    public function markOldSubscriptionCancelled(int $transitionId): void
    {
        SubscriptionPricingChangeTransition::where('id', $transitionId)->update([
            'status' => SubscriptionPricingChangeTransitionStatus::OldSubscriptionCancelled->value,
        ]);
    }

    public function markNewSubscriptionCreated(
        int $transitionId,
        int $newSubscriptionId,
        ?string $newStripeSubscriptionId
    ): void {
        SubscriptionPricingChangeTransition::where('id', $transitionId)->update([
            'new_subscription_id' => $newSubscriptionId,
            'new_stripe_subscription_id' => $newStripeSubscriptionId,
            'status' => SubscriptionPricingChangeTransitionStatus::NewSubscriptionCreated->value,
        ]);
    }

    public function markItdGenerated(int $transitionId): void
    {
        SubscriptionPricingChangeTransition::where('id', $transitionId)->update([
            'status' => SubscriptionPricingChangeTransitionStatus::ItdGenerated->value,
        ]);
    }

    public function markCompleted(int $transitionId): void
    {
        SubscriptionPricingChangeTransition::where('id', $transitionId)->update([
            'status' => SubscriptionPricingChangeTransitionStatus::Completed->value,
        ]);
    }

    public function markFailed(int $transitionId, string $reason): void
    {
        SubscriptionPricingChangeTransition::where('id', $transitionId)->update([
            'status' => SubscriptionPricingChangeTransitionStatus::Failed->value,
            'failure_reason' => $reason,
        ]);
    }

    protected function getModelClass(): string
    {
       return SubscriptionPricingChangeTransition::class;
    }
}