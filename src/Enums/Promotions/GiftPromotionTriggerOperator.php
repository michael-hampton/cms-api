<?php

namespace App\Enums\Promotions;

enum GiftPromotionTriggerOperator: string
{
    case EQUALS = 'equals';
    case NOT_EQUALS = 'not_equals';
    case GREATER_THAN = 'greater_than';
    case LESS_THAN = 'less_than';
    case GREATER_THAN_OR_EQUAL = 'greater_than_or_equal';
    case LESS_THAN_OR_EQUAL = 'less_than_or_equal';

    public function label(): string
    {
        return match ($this) {
            self::EQUALS => '=',
            self::NOT_EQUALS => '≠',
            self::GREATER_THAN => '>',
            self::LESS_THAN => '<',
            self::GREATER_THAN_OR_EQUAL => '≥',
            self::LESS_THAN_OR_EQUAL => '≤',
        };
    }
}