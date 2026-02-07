<?php

namespace App\Enums\Newsletters;

enum PremiumAccessType: string
{
    case Newsletter = 'newsletter';
    case Archive = 'archive';
    case Video = 'video';
    case Podcast = 'podcast';
    case EarlyAccess = 'early_access';

    public function label(): string
    {
        return match ($this) {
            self::Newsletter => 'Premium Newsletters',
            self::Archive => 'Content Archive',
            self::Video => 'Video Content',
            self::Podcast => 'Podcast Episodes',
            self::EarlyAccess => 'Early Access',
        };
    }
}