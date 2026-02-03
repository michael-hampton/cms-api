<?php

namespace App\Enums;

enum RewardStatus: string
{
    case PENDING = 'pending';
    case CLAIMED = 'claimed';
    case EXPIRED = 'expired';
    case DECLINED = 'declined';
}