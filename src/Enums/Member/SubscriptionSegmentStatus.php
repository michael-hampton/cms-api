<?php

namespace App\Enums\Member;

enum SubscriptionSegmentStatus: string
{
    case Active   = 'active';
    case Expired  = 'expired';
    case Replaced = 'replaced';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}