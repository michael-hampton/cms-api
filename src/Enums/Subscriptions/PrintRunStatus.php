<?php

namespace App\Enums\Subscriptions;

enum PrintRunStatus: string
{
    case PENDING = 'pending';
    case FULFILLING = 'fulfilling';
    case BATCHED = 'batched';
    case BATCHING = 'batching';
    case COMPLETE = 'complete';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETE, self::CANCELLED, self::FAILED => true,
            default => false,
        };
    }

    public function canCancel(): bool
    {
        return $this === self::PENDING;
    }
}