<?php

namespace App\Enums\Blocks;

enum ImageLayout: string
{
    case INLINE = 'inline';
    case FULL = 'full';
    case RESPONSIVE = 'responsive';
    case FLUID = 'fluid';
    case FIXED = 'fixed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}