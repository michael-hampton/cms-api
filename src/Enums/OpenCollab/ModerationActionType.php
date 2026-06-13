<?php

namespace App\Enums\OpenCollab;

enum ModerationActionType: string
{
    case Submitted = 'submitted';
    case Claimed = 'claimed';
    case Released = 'released';
    case ReviewStarted = 'review_started';
    case RiskAdded = 'risk_added';
    case RiskResolved = 'risk_resolved';
    case ChangesRequested = 'changes_requested';
    case Resubmitted = 'resubmitted';
    case Escalated = 'escalated';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case PriorityOverridden = 'priority_overridden';
}