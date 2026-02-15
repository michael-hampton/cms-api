<?php

namespace App\Enums\Refunds;

enum RefundType: string
{
    case FULL = 'full';
    case PARTIAL = 'partial';
}
