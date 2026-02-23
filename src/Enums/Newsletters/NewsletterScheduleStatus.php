<?php

namespace App\Enums\Newsletters;

enum NewsletterScheduleStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isRunnable(): bool
    {
        return $this === self::ACTIVE;
    }
}