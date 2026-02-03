<?php

namespace App\Services\Rewards\Handlers;

use App\Models\Member;
use App\Models\RewardDefinition;

class DiscountRewardHandler implements RewardTypeHandlerInterface
{
    private const DEFAULT_EXPIRY_DAYS = 30;

    public function handle(Member $member, RewardDefinition $definition, int $siteId): ?array
    {
        $rewardData = [
            'discount_type' => $definition->reward_config['discount_type'] ?? 'percentage',
            'discount_value' => $definition->reward_config['discount_value'] ?? 10
        ];

        $expiryDays = $definition->reward_config['expiry_days'] ?? self::DEFAULT_EXPIRY_DAYS;
        $expiresAt = now_datetime()->modify("+{$expiryDays} days");

        return [
            'reward_data' => $rewardData,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s')
        ];
    }
}