<?php
// src/Services/Rewards/Handlers/RewardTypeHandlerFactory.php

namespace App\Services\Rewards\Handlers;

use App\Enums\RewardType;

class RewardTypeHandlerFactory
{
    public function __construct(
        private readonly VoucherRewardHandler  $voucherHandler,
        private readonly DiscountRewardHandler $discountHandler,
        private readonly PointsRewardHandler   $pointsHandler
    )
    {
    }

    public function make(RewardType $type): RewardTypeHandlerInterface
    {
        return match ($type) {
            RewardType::VOUCHER => $this->voucherHandler,
            RewardType::DISCOUNT => $this->discountHandler,
            RewardType::POINTS => $this->pointsHandler,
        };
    }
}