<?php

namespace App\Enums;

enum ImportType: string
{
    case Voucher = 'voucher';
    case Offer = 'offer';
    case Product = 'product';
}