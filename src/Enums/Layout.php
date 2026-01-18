<?php

namespace App\Enums;

enum Layout: string
{
    case STANDARD = 'standard';
    case COMPACT = 'compact';
    case WIDE = 'wide';
    case GRID = 'grid';
    case CAROUSEL = 'carousel';
    case MASONRY = 'masonry';
    case HORIZONTAL = 'horizontal';
    case VERSUS = 'versus';
    case SLIDER = 'slider';
    case HERO = 'hero';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}