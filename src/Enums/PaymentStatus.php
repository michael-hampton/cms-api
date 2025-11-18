<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PAID = 'paid';
    case UNPAID = 'unpaid';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
