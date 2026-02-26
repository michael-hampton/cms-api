<?php

namespace App\Services\Shopping;

use App\DTO\Checkout\EligibilityResult;

class CartSubscriptionUniquenessRule
{
    public function filterInvalidItems(array $cartItems): EligibilityResult
    {
        $seen = [];
        $valid = [];
        $removed = [];

        foreach ($cartItems as $item) {
            if (empty($item['subscription_plan_id'])) {
                $valid[] = $item;
                continue;
            }

            $planId = (int)$item['subscription_plan_id'];

            if (isset($seen[$planId])) {
                $removed[] = $item;
            } else {
                $seen[$planId] = true;
                $valid[] = $item;
            }
        }

        return new EligibilityResult(
            valid: array_values($valid),
            removed: $removed
        );
    }
}