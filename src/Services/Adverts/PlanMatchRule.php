<?php

namespace App\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Models\Member;

class PlanMatchRule implements EligibilityRule
{
    public function __construct(
        private readonly string $requiredPlanSlug
    )
    {
    }

    public function evaluate(?Member $member): VisibilityDecision
    {
        if (!$member) {
            return VisibilityDecision::hide(SuppressionReason::NOT_AUTHENTICATED);
        }

        $subscription = $member->activeSubscription(false, $member->site_id);

        if (!$subscription || !$subscription->plan) {
            return VisibilityDecision::hide(SuppressionReason::PLAN_MISMATCH);
        }

        if ($subscription->plan->slug !== $this->requiredPlanSlug) {
            return VisibilityDecision::hide(SuppressionReason::PLAN_MISMATCH);
        }

        return VisibilityDecision::show();
    }
}