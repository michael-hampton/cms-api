<?php

namespace App\Enums\Subscriptions;

enum SubscriptionDeliveryType: string
{
    case DIGITAL = 'digital';
    case PRINT = 'print';
    case PRINT_AND_DIGITAL = 'print_and_digital';

    public static function values(): array
    {
        return array_map(static fn(self $type) => $type->value, self::cases());
    }

    public function includesDigital(): bool
    {
        return $this === self::DIGITAL || $this === self::PRINT_AND_DIGITAL;
    }

    public function includesPrint(): bool
    {
        return $this === self::PRINT || $this === self::PRINT_AND_DIGITAL;
    }
}
