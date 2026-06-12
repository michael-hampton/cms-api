<?php

namespace App\Enums\Pages;

enum PageStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
    case SCHEDULED = 'scheduled';
    case WAITING_APPROVAL = 'waiting_approval';
    case PRIVATE = 'private';
    case ON_HOLD = 'on_hold';
    case REJECTED = 'rejected';

    case INTERNAL = 'internal';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
            self::SCHEDULED => 'Scheduled',
            self::WAITING_APPROVAL => 'Waiting Approval',
            self::PRIVATE => 'Private',
            self::ON_HOLD => 'On Hold',
            self::REJECTED => 'Rejected',
            self::INTERNAL => 'Internal',
        };
    }
}
