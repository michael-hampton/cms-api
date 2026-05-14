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
        $nonGiftItems = array_filter(
            $cartItems,
            fn($item) => empty($item['gift_email']) && empty($item['is_gift'])
        );

        $subscriptionPlanIds = array_unique(
            array_filter(
                array_column($nonGiftItems, 'subscription_plan_id')
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
            $isGift = !empty($item['gift_email']) || !empty($item['is_gift']);

            if (
                !$isGift &&
                !empty($item['subscription_plan_id']) &&
                isset($activePlanIds[$item['subscription_plan_id']])
            ) {
                $removed[] = $item;
            } else {
                $valid[] = $item;
            }
        }

        return new EligibilityResult(
            valid: array_values($valid),
            removed: $removed
        );
    }
}