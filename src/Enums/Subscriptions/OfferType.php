<?php

namespace App\Enums\Subscriptions;

enum OfferType: string
{
    case PRINT   = 'print';
    case DIGITAL = 'digital';
    case INTRO   = 'intro';
    case VOUCHER = 'voucher';
}