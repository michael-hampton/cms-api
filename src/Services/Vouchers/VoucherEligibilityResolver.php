<?php

namespace App\Services\Vouchers;

use App\Models\Voucher;

class VoucherEligibilityResolver
{
    public function resolveEligibleItems(Voucher $voucher, array $cartItems): array
    {
        $eligible = [];

        foreach ($cartItems as $item) {
            if ($this->isItemEligible($voucher, $item)) {
                $eligible[] = $item;
            }
        }

        return $eligible;
    }

    private function isItemEligible(Voucher $voucher, array $item): bool
    {
        // Check product eligibility
        if (isset($item['product_id']) && $voucher->isApplicableToProduct($item['product_id'])) {
            return true;
        }

        // Check subscription plan eligibility
        if (isset($item['subscription_plan_id']) && $voucher->isApplicableToSubscriptionPlan($item['subscription_plan_id'])) {
            return true;
        }

        return false;
    }
}