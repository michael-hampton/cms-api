<?php

namespace App\Enums\Subscriptions;

enum PricingEntitlementType: string
{
    case TIME = 'time';
    case ISSUES = 'issues';

    public static function values(): array
    {
        return array_map(static fn(self $type) => $type->value, self::cases());
    }
}
