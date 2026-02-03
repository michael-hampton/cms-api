<?php

namespace App\Services\Analytics;

use App\Framework\Database\Database;
use App\Models\RewardDefinition;
use App\Models\MemberReward;
use Exception;

class RewardAnalyticsService
{
    public function getRewardDefinitionStatistics(int $definitionId): array
    {
        $definition = RewardDefinition::find($definitionId);

        if (!$definition) {
            throw new Exception('Reward definition not found');
        }

        $memberRewards = MemberReward::where('reward_definition_id', $definitionId)->get();

        $totalRewards = $memberRewards->count();
        $claimedRewards = $memberRewards->where('status', 'claimed')->count();
        $pendingRewards = $memberRewards->where('status', 'pending')->count();
        $expiredRewards = $memberRewards->where('status', 'expired')->count();
        $declinedRewards = $memberRewards->where('status', 'declined')->count();

        $clickStats = $this->getClickStatistics($memberRewards->pluck('id')->toArray());

        return [
            'definition_id' => $definitionId,
            'definition_name' => $definition->name,
            'total_rewards' => $totalRewards,
            'claimed' => $claimedRewards,
            'pending' => $pendingRewards,
            'expired' => $expiredRewards,
            'declined' => $declinedRewards,
            'claim_rate' => $totalRewards > 0
                ? round(($claimedRewards / $totalRewards) * 100, 2)
                : 0,
            'total_clicks' => $clickStats['total'],
            'unique_clickers' => $clickStats['unique'],
            'click_through_rate' => $clickStats['click_through_rate'],
            'clicks_by_action' => $clickStats['by_action'],
            'recent_clicks' => $clickStats['recent']
        ];
    }

    private function getClickStatistics(array $memberRewardIds): array
    {
        if (empty($memberRewardIds)) {
            return [
                'total' => 0,
                'unique' => 0,
                'click_through_rate' => 0,
                'by_action' => [],
                'recent' => []
            ];
        }

        $totalClicks = Database::table('reward_clicks')
            ->whereIn('member_reward_id', $memberRewardIds)
            ->count();

        $uniqueClickers = Database::table('reward_clicks')
            ->whereIn('member_reward_id', $memberRewardIds)
            ->distinct('member_id')
            ->count('member_id');

        $clicksByAction = Database::table('reward_clicks')
            ->whereIn('member_reward_id', $memberRewardIds)
            ->select('action', Database::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->get()
            ->pluck('count', 'action')
            ->toArray();

        $recentClicks = Database::table('reward_clicks as rc')
            ->join('member_rewards as mr', 'rc.member_reward_id', '=', 'mr.id')
            ->join('members as m', 'rc.member_id', '=', 'm.id')
            ->whereIn('rc.member_reward_id', $memberRewardIds)
            ->select(
                'rc.created_at',
                'rc.action',
                'm.first_name',
                'm.last_name',
                'm.email',
                'mr.id as reward_id'
            )
            ->orderByDesc('rc.created_at')
            ->limit(10)
            ->get()
            ->map(fn($click) => [
                'clicked_at' => $click->created_at,
                'action' => $click->action,
                'member_name' => "{$click->first_name} {$click->last_name}",
                'member_email' => $click->email,
                'reward_id' => $click->reward_id
            ])
            ->toArray();

        $clickThroughRate = count($memberRewardIds) > 0
            ? round(($uniqueClickers / count($memberRewardIds)) * 100, 2)
            : 0;

        return [
            'total' => $totalClicks,
            'unique' => $uniqueClickers,
            'click_through_rate' => $clickThroughRate,
            'by_action' => $clicksByAction,
            'recent' => $recentClicks
        ];
    }
}