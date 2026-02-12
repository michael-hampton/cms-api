<?php

namespace App\Enums;

enum Alignment: string
{
    case LEFT = 'left';
    case RIGHT = 'right';
    case CENTER = 'center';
    case FULLSCREEN = 'fullscreen';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}