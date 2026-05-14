<?php

namespace App\Services\Shopping;

use App\DTO\Checkout\EligibilityResult;
use App\Models\Member;

class CheckoutEligibilityService
{
    public function __construct(
        private readonly CartSubscriptionUniquenessRule $uniquenessRule,
        private readonly SubscriptionEligibilityRule    $subscriptionRule
    )
    {
    }

    public function validate(Member $user, array $cartItems): EligibilityResult
    {
        $uniqueness = $this->uniquenessRule->filterInvalidItems($cartItems);

        $eligibility = $this->subscriptionRule->filterInvalidItems($user, $uniqueness->valid);

        return new EligibilityResult(
            valid: $eligibility->valid,
            removed: array_merge($uniqueness->removed, $eligibility->removed)
        );
    }
}