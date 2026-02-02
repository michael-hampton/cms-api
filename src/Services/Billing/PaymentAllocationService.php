<?php

namespace App\Services\Billing;

/**
 * Allocates a checkout-level payment across merchant order groups
 * proportionally by each group's subtotal share.
 *
 * Each returned allocation contains: subtotal, tax, shipping, discount, total.
 * Rounding is handled by giving any penny remainder to the last (largest)
 * group so the sum is always exact.
 */
class PaymentAllocationService
{
    /**
     * @param array $groups Output of CheckoutSplittingService::splitByMerchant()
     * @param array $checkoutTotals Keys: subtotal, tax, shipping, discount, total
     * @param array $shippingPerGroup Keyed by the same merchant keys, value = shipping cost (float)
     * @return array  Same keys as $groups, values are allocation arrays.
     */
    public function allocate(array $groups, array $checkoutTotals, array $shippingPerGroup = []): array
    {
        if (empty($groups)) {
            return [];
        }

        // 1. Calculate each group's raw subtotal
        $groupSubtotals = [];
        $grandSubtotal = 0.0;

        foreach ($groups as $key => $group) {
            $subtotal = 0.0;
            foreach ($group['items'] as $item) {
                $subtotal += ($item['price'] * $item['quantity']);
            }
            $groupSubtotals[$key] = $subtotal;
            $grandSubtotal += $subtotal;
        }

        $count = count($groups);

        // 2. Sort keys by subtotal descending so the largest group is last
        //    and deterministically absorbs any rounding remainder.
        $keys = array_keys($groups);
        usort($keys, function ($a, $b) use ($groupSubtotals) {
            // Descending: largest first, smallest last (last absorbs remainder)
            return $groupSubtotals[$b] <=> $groupSubtotals[$a];
        });

        // 3. Compute proportional shares for tax and discount (shipping is per-group)
        $allocations = [];
        $taxRemaining = (float)$checkoutTotals['tax'];
        $discountRemaining = (float)$checkoutTotals['discount'];

        foreach ($keys as $i => $key) {
            $isLast = ($i === count($keys) - 1);

            $proportion = $grandSubtotal > 0
                ? $groupSubtotals[$key] / $grandSubtotal
                : 1.0 / $count;

            $groupSubtotal = round($groupSubtotals[$key], 2);

            // Tax allocation
            if ($isLast) {
                $groupTax = round($taxRemaining, 2);
            } else {
                $groupTax = round((float)$checkoutTotals['tax'] * $proportion, 2);
                $taxRemaining -= $groupTax;
            }

            // Discount allocation
            if ($isLast) {
                $groupDiscount = round($discountRemaining, 2);
            } else {
                $groupDiscount = round((float)$checkoutTotals['discount'] * $proportion, 2);
                $discountRemaining -= $groupDiscount;
            }

            // Shipping is already per-group (from MerchantShippingService)
            $groupShipping = round($shippingPerGroup[$key] ?? 0.0, 2);

            $groupTotal = round($groupSubtotal + $groupTax + $groupShipping - $groupDiscount, 2);

            $allocations[$key] = [
                'subtotal' => $groupSubtotal,
                'tax' => $groupTax,
                'shipping' => $groupShipping,
                'discount' => $groupDiscount,
                'total' => $groupTotal,
                'stripe_eligible' => $groupTotal > 0,
            ];
        }

        return $allocations;
    }
}