<?php

namespace App\Enums;

enum Currency: string
{
    case GBP_SYMBOL = '£';
    case USD_SYMBOL = '$';
    case EUR_SYMBOL = '€';
    case YEN_SYMBOL = '¥';
    case USD = 'USD';
    case GBP = 'GBP';
    case EUR = 'EUR';
    case JPY = 'JPY';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}