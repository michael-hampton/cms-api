<?php

namespace App\Enums;

enum GalleryLayout: string
{
    case CAROUSEL = 'carousel';
    case GRID = 'grid';
    case MASONRY = 'masonry';
    case SLIDER = 'slider';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}