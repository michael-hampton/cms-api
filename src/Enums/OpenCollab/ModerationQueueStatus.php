<?php

namespace App\Enums\OpenCollab;

enum ModerationQueueStatus: string
{
    case Queued = 'queued';
    case Claimed = 'claimed';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Escalated = 'escalated';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isClosed(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled], true);
    }

    public function isOpen(): bool
    {
        return !$this->isClosed();
    }
}