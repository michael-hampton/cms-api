<?php

namespace App\Enums;

enum BadgeCriteriaOperator: string
{
    case GREATER_THAN_OR_EQUAL = '>=';
    case GREATER_THAN = '>';
    case LESS_THAN_OR_EQUAL = '<=';
    case LESS_THAN = '<';
    case EQUALS = '==';
    case NOT_EQUALS = '!=';

    public function compare($actual, $expected): bool
    {
        return match ($this) {
            self::GREATER_THAN_OR_EQUAL => $actual >= $expected,
            self::GREATER_THAN => $actual > $expected,
            self::LESS_THAN_OR_EQUAL => $actual <= $expected,
            self::LESS_THAN => $actual < $expected,
            self::EQUALS => $actual == $expected,
            self::NOT_EQUALS => $actual != $expected,
        };
    }
}