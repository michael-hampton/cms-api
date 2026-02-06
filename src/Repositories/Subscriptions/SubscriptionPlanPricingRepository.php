<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Repository;

class SubscriptionPlanPricingRepository extends Repository
{
    public function getForPlan(int $planId): Collection
    {
        $query = SubscriptionPlanPricing::where('is_active', true);

        if ($planId) {
            $query->where('plan_id', $planId);
        }

        return $query->orderBy('sort_order')->get();
    }

    public function getDefaultForPlan(int $planId): ?SubscriptionPlanPricing
    {
        return SubscriptionPlanPricing::where('plan_id', $planId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    public function setAsDefault(int $pricingId): bool
    {
        $pricing = $this->find($pricingId);

        if (!$pricing) {
            return false;
        }

        // Remove default from all other pricing tiers for this plan
        SubscriptionPlanPricing::where('plan_id', $pricing->plan_id)
            ->where('id', '!=', $pricingId)
            ->update(['is_default' => false]);

        // Set this as default
        return $this->update($pricingId, ['is_default' => true]) !== null;
    }

    public function toggleActive(int $pricingId): bool
    {
        $pricing = $this->find($pricingId);

        if (!$pricing) {
            return false;
        }

        return $this->update($pricingId, [
                'is_active' => !$pricing->is_active
            ]) !== null;
    }

    public function updateSortOrders(array $orderMap): bool
    {
        foreach ($orderMap as $id => $sortOrder) {
            $this->update($id, ['sort_order' => $sortOrder]);
        }

        return true;
    }

    protected function getModelClass(): string
    {
        return SubscriptionPlanPricing::class;
    }
}