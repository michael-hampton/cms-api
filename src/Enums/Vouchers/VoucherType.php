<?php

namespace App\Enums\Vouchers;

enum VoucherType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}