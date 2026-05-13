<?php

namespace App\Enums\OpenCollab;

enum GuidelineStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isPublishable(): bool
    {
        return $this === self::Draft;
    }

    public function isArchivable(): bool
    {
        return $this === self::Published;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }
}