<?php

namespace App\Enums\Member;

enum SubscriptionSegmentSource: string
{
    case RuleBased = 'rule_based';
    case Manual    = 'manual';
    case Webhook   = 'webhook';
    case Batch     = 'batch';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}