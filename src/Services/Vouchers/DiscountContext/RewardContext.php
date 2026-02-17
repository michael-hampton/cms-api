<?php

namespace App\Services\Vouchers\DiscountContext;

final class RewardContext
{
    public function __construct(
        public readonly int $rewardId,
    )
    {
    }
}