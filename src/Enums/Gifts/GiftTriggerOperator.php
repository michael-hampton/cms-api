<?php

namespace App\Enums\Gifts;

enum GiftTriggerOperator: string
{
    case EQUALS = '=';
    case IN = 'in';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN_OR_EQUAL = '<=';
}