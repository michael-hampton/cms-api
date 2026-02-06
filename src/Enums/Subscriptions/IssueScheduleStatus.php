<?php

namespace App\Enums\Subscriptions;

enum IssueScheduleStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::CANCELLED => 'Cancelled',
        };
    }
}