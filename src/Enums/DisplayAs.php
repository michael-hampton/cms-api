<?php

namespace App\Enums;

enum DisplayAs: string
{
    case BUTTON = 'button';
    case LINK = 'link';
    case CARD = 'card';
    case INLINE = 'inline';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}