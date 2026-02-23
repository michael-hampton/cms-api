<?php

namespace App\Enums\Newsletters;

enum LayoutVersionState: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Published = 'published';
    case Deprecated = 'deprecated';

    public function canBeUsedForNewNewsletters(): bool
    {
        return $this === self::Published;
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::Validated,
            self::Validated => $next === self::Published,
            self::Published => $next === self::Deprecated,
            self::Deprecated => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Validated => 'Validated',
            self::Published => 'Published',
            self::Deprecated => 'Deprecated',
        };
    }
}