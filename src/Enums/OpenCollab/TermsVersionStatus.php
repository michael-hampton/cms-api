<?php

namespace App\Enums\OpenCollab;

enum TermsVersionStatus: string
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
}
