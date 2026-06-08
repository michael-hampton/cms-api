<?php

namespace App\Enums\OpenCollab;

enum PageVisibility: string
{
    case Free    = 'free';
    case Premium = 'premium';
    case Hidden  = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Free    => 'Free',
            self::Premium => 'Premium',
            self::Hidden  => 'Hidden',
        };
    }

    public function isPremium(): bool
    {
        return $this === self::Premium;
    }
}