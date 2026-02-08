<?php

namespace App\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Models\Member;

class RequirePaidRule implements EligibilityRule
{
    public function evaluate(?Member $member): VisibilityDecision
    {
        if (!$member) {
            return VisibilityDecision::hide(SuppressionReason::NOT_AUTHENTICATED);
        }

        if (!$member->isPaid()) {
            return VisibilityDecision::hide(SuppressionReason::REQUIRES_PAID_MEMBERSHIP);
        }

        return VisibilityDecision::show();
    }
}