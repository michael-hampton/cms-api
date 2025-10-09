<?php

namespace App\Enums;

enum SchemaType: string
{
    case NONE = 'none';
    case STEPS = 'steps';
    case INGREDIENTS = 'ingredients';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}