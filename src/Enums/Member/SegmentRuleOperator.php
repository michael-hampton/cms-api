<?php

namespace App\Enums\Member;

enum SegmentRuleOperator: string
{
    case GREATER_THAN = '>';
    case LESS_THAN = '<';
    case EQUALS = '=';
    case NOT_EQUALS = '!=';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN_OR_EQUAL = '<=';
    case IN = 'IN';
    case CONTAINS = 'CONTAINS';

    public function compare(mixed $actual, mixed $expected): bool
    {
        return match ($this) {
            self::GREATER_THAN => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            self::LESS_THAN => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            self::GREATER_THAN_OR_EQUAL => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            self::LESS_THAN_OR_EQUAL => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            self::EQUALS => $actual == $expected,
            self::NOT_EQUALS => $actual != $expected,
            self::IN => in_array($actual, (array)$expected, strict: false),
            self::CONTAINS => in_array($expected, (array)$actual, strict: false),
        };
    }
}