<?php

namespace App\Enums\OpenCollab;

enum EscalationStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Overdue = 'overdue';

    public function isResolved(): bool
    {
        return in_array($this, [self::Resolved, self::Closed, self::Cancelled], true);
    }
}