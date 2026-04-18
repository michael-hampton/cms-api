<?php

namespace App\Enums\Member;

enum SegmentRuleBoolean: string
{
    case AND = 'AND';
    case OR = 'OR';
}