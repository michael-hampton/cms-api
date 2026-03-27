<?php

namespace App\Enums\Address;

enum AddressType: string
{
    case Shipping = 'shipping';
    case Billing = 'billing';
    case Both = 'both';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Shipping => 'Shipping',
            self::Billing => 'Billing',
            self::Both => 'Both (Shipping & Billing)',
        };
    }
}