<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

enum LabelRunStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Complete = 'complete';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Complete, self::Failed => true,
            default => false,
        };
    }

    public function canRetry(): bool
    {
        return $this === self::Failed;
    }
}