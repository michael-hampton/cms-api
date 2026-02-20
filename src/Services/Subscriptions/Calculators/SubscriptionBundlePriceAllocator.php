<?php

namespace App\Services\Subscriptions\Calculators;

use App\Models\SubscriptionBundle;

/**
 * Allocates a bundle's single bundle_price across its constituent subscription
 * plans proportionally by list price.
 *
 * Design rule: calculations and decisions live in dedicated collaborators, not
 * in the service. This class has exactly one reason to change: the price
 * allocation algorithm.
 *
 * Allocation strategy:
 *   - Each plan's share = (plan_list_price / total_list_price) * bundle_price
 *   - Floating-point remainder is assigned to the last plan to guarantee
 *     the per-item prices sum to exactly bundle_price (in cents).
 *
 * Why cents internally?  Avoids accumulating float errors during distribution.
 */
class SubscriptionBundlePriceAllocator
{
    /**
     * @param SubscriptionBundle $bundle
     * @return array<int, float>  Map of subscription_plan_id => allocated price (dollars)
     *
     * @throws \RuntimeException  If total list price is zero (cannot distribute).
     */
    public function allocate(SubscriptionBundle $bundle): array
    {
        $items = $bundle->items;

        if ($items->isEmpty()) {
            return [];
        }

        // Build plan list-prices for each item.
        $planListPriceCents = [];
        foreach ($items as $item) {
            $plan = $item->subscriptionPlan;
            $planListPriceCents[$item->subscription_plan_id] = (int)round($plan->price * 100);
        }

        $totalListCents = array_sum($planListPriceCents);

        if ($totalListCents === 0) {
            throw new \RuntimeException(
                "Cannot allocate bundle price: all constituent plans have zero list price."
            );
        }

        $bundlePriceCents = (int)round($bundle->bundle_price * 100);
        $allocated = [];
        $allocatedSoFar = 0;
        $planIds = array_keys($planListPriceCents);
        $lastIndex = count($planIds) - 1;

        foreach ($planIds as $index => $planId) {
            if ($index === $lastIndex) {
                // Remainder goes to the last plan – guarantees exact sum.
                $share = $bundlePriceCents - $allocatedSoFar;
            } else {
                $share = (int)floor(
                    ($planListPriceCents[$planId] / $totalListCents) * $bundlePriceCents
                );
                $allocatedSoFar += $share;
            }

            $allocated[$planId] = round($share / 100, 2);
        }

        return $allocated;
    }
}