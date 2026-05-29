<?php

namespace App\Enums\Member;

/**
 * Operators supported by the SegmentRuleEngine for subscription/plan segments.
 *
 * These are intentionally separate from the existing SegmentRuleOperator enum
 * (which handles member segment rules) because subscription rules support
 * date-oriented and domain-specific operators that have no meaning in the
 * member segmentation context.
 */
enum SubscriptionRuleOperator: string
{
    case Equals          = 'equals';
    case NotEquals       = 'not_equals';
    case GreaterThan     = 'greater_than';
    case LessThan        = 'less_than';
    case Between         = 'between';
    case Contains        = 'contains';
    case In              = 'in';
    case NotIn           = 'not_in';
    case Before          = 'before';
    case After           = 'after';
    case WithinNextDays  = 'within_next_days';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}