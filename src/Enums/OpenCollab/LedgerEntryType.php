<?php

namespace App\Enums\OpenCollab;

enum LedgerEntryType: string
{
    case Sale = 'sale';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
