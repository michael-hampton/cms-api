<?php

namespace App\Enums;

enum ListType: string
{
    case UNORDERED = 'ul';
    case ORDERED = 'ol';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}