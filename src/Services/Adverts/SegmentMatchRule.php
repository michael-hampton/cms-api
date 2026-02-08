<?php

namespace App\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Models\Member;

class SegmentMatchRule implements EligibilityRule
{
    public function __construct(
        private readonly array                $allowedSegments,
        private readonly MemberSegmentChecker $segmentChecker
    )
    {
    }

    public function evaluate(?Member $member): VisibilityDecision
    {
        if (!$member) {
            return VisibilityDecision::hide(SuppressionReason::NOT_AUTHENTICATED);
        }

        if (!$this->segmentChecker->isInAnySegment($member, $this->allowedSegments)) {
            return VisibilityDecision::hide(SuppressionReason::SEGMENT_MISMATCH);
        }

        return VisibilityDecision::show();
    }
}