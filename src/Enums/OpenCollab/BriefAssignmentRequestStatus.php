<?php

namespace App\Enums\OpenCollab;

enum BriefAssignmentRequestStatus: string
{
    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Resolved  = 'resolved';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Approved,
            self::Rejected,
            self::Resolved,
            self::Cancelled => true,
            self::Pending   => false,
        };
    }
}