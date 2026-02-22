<?php

namespace App\Enums\Rewards;

enum RewardStatus: string
{
    case PENDING = 'pending';
    case CLAIMED = 'claimed';
    case EXPIRED = 'expired';
    case DECLINED = 'declined';
    case APPROVED = 'approved';
}