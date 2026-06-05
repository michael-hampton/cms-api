<?php

namespace App\Enums\Subscriptions;

enum SubscriptionEntitlementType: string
{
    case TIME = 'time';
    case ISSUES = 'issues';
    case MIXED = 'mixed';

    public static function values(): array
    {
        return array_map(static fn(self $type) => $type->value, self::cases());
    }
}
