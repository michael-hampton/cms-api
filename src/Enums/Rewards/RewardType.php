<?php

namespace App\Enums\Rewards;

enum RewardType: string
{
    case VOUCHER = 'voucher';
    case DISCOUNT = 'discount';
    case POINTS = 'points';
}