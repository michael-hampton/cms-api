<?php

namespace App\Services\Shopping;

use App\DTO\Checkout\EligibilityResult;
use App\Models\Member;
use App\Repositories\Subscriptions\SubscriptionRepository;

class SubscriptionEligibilityRule
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions
    )
    {
    }

    public function filterInvalidItems(Member $user, array $cartItems): EligibilityResult
    {
        $subscriptionPlanIds = array_unique(
            array_filter(
                array_column($cartItems, 'subscription_plan_id')
            )
        );

        if (empty($subscriptionPlanIds)) {
            return new EligibilityResult(valid: $cartItems, removed: []);
        }

        $activePlanIds = array_flip(
            $this->subscriptions->getActivePlanIds(
                $user->id,
                array_values($subscriptionPlanIds)
            )
        );

        $valid = [];
        $removed = [];

        foreach ($cartItems as $item) {
//            if (
//                !empty($item['subscription_plan_id']) &&
//                isset($activePlanIds[$item['subscription_plan_id']])
//            ) {
//                $removed[] = $item;
//            } else {
                $valid[] = $item;
            //}
        }

        return new EligibilityResult(
            valid: array_values($valid),
            removed: $removed
        );
    }
}