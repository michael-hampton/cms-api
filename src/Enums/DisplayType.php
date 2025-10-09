<?php

namespace App\Enums;

enum DisplayType: string
{
    case PROFILE = 'profile';
    case CONTACT = 'contact';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}