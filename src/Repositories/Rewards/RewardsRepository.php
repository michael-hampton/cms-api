<?php

namespace App\Repositories\Rewards;

use App\Framework\Support\Collection;
use App\Models\MemberReward;
use App\Models\RewardDefinition;
use App\Models\RewardVoucherCode;
use App\Repositories\Repository;

class RewardsRepository extends Repository
{
    public function getActiveRewardDefinitions(int $siteId): Collection
    {
        return RewardDefinition::where('site_id', $siteId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getMemberRewards(int $memberId, int $siteId, ?string $status = null): Collection
    {
        $query = MemberReward::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->with(['rewardDefinition', 'voucherCode']);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('earned_at', 'desc')->get();
    }

    public function hasEarnedReward(int $memberId, int $rewardDefinitionId): bool
    {
        return MemberReward::where('member_id', $memberId)
            ->where('reward_definition_id', $rewardDefinitionId)
            ->exists();
    }

    public function countMemberRewards(int $memberId, int $rewardDefinitionId): int
    {
        return MemberReward::where('member_id', $memberId)
            ->where('reward_definition_id', $rewardDefinitionId)
            ->count();
    }

    public function createMemberReward(array $data): MemberReward
    {
        $data['earned_at'] = $data['earned_at'] ?? now_datetime();
        $data['status'] = $data['status'] ?? 'pending';

        return MemberReward::create($data);
    }

    public function getAvailableVoucher(int $rewardDefinitionId): ?RewardVoucherCode
    {
        return RewardVoucherCode::where('reward_definition_id', $rewardDefinitionId)
            ->where('is_used', false)
            ->whereNull('assigned_to_member_id')
            ->first();
    }

    public function markExpiredRewards(int $siteId): int
    {
        return MemberReward::where('site_id', $siteId)
            ->where('status', 'pending')
            ->where('expires_at', '<', now_datetime())
            ->update(['status' => 'expired']);
    }

    public function findMemberRewardById(int $rewardId): ?MemberReward
    {
        return MemberReward::with(['rewardDefinition', 'voucherCode'])
            ->find($rewardId);
    }

    protected function getModelClass(): string
    {
        return MemberReward::class;
    }
}