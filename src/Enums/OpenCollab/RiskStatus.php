<?php

namespace App\Enums\OpenCollab;

enum RiskStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Cleared = 'cleared';
    case Confirmed = 'confirmed';
    case Escalated = 'escalated';
    case Dismissed = 'dismissed';

    /**
     * Statuses that still count against priority/governance.
     */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::Open, self::UnderReview, self::Confirmed, self::Escalated], true);
    }
}