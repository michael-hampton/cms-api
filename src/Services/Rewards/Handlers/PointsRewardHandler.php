<?php

namespace App\Services\Rewards\Handlers;

use App\Models\Member;
use App\Models\RewardDefinition;

class PointsRewardHandler implements RewardTypeHandlerInterface
{
    public function handle(Member $member, RewardDefinition $definition, int $siteId): ?array
    {
        $rewardData = [
            'points' => $definition->reward_config['points'] ?? 100
        ];

        return [
            'reward_data' => $rewardData,
            'expires_at' => null
        ];
    }
}