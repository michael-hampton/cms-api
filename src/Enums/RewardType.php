<?php

namespace App\Enums;

enum RewardType: string
{
    case VOUCHER = 'voucher';
    case DISCOUNT = 'discount';
    case POINTS = 'points';
}