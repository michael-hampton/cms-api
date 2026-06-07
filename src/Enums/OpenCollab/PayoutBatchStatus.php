<?php

namespace App\Enums\OpenCollab;

enum PayoutBatchStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case PartiallyFailed = 'partially_failed';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Failed,
            self::Cancelled,
        ], true);
    }
}