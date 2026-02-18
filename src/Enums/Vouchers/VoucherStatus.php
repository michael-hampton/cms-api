<?php

namespace App\Enums\Vouchers;

enum VoucherStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';
}