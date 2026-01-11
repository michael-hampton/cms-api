<?php

namespace App\Repositories\Rewards;

use App\Framework\Support\Collection;
use App\Models\MemberReward;
use App\Models\RewardClick;
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
        $data['earned_at'] = $data['earned_at'] ?? now_datetime()->format('Y-m-d H:i:s');
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

    public function searchRewards(int $siteId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $query = MemberReward::where('site_id', $siteId)
            ->with(['member', 'rewardDefinition', 'voucherCode']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by member
        if (!empty($filters['member_id'])) {
            $query->where('member_id', $filters['member_id']);
        }

        // Filter by reward definition
        if (!empty($filters['reward_definition_id'])) {
            $query->where('reward_definition_id', $filters['reward_definition_id']);
        }

        // Filter by date range
        if (!empty($filters['date_from'])) {
            $query->where('earned_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('earned_at', '<=', $filters['date_to']);
        }

        // Search by member name or email
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('member', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        $rewards = $query->orderBy('earned_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $rewards,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage)
        ];
    }

    public function getRewardStats(int $siteId): array
    {
        return [
            'total' => MemberReward::where('site_id', $siteId)->count(),
            'pending' => MemberReward::where('site_id', $siteId)->where('status', 'pending')->count(),
            'claimed' => MemberReward::where('site_id', $siteId)->where('status', 'claimed')->count(),
            'expired' => MemberReward::where('site_id', $siteId)->where('status', 'expired')->count(),
            'declined' => MemberReward::where('site_id', $siteId)->where('status', 'declined')->count()
        ];
    }

    public function trackClick(int $memberRewardId, int $memberId, int $siteId, string $action, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        RewardClick::create([
            'member_reward_id' => $memberRewardId,
            'member_id' => $memberId,
            'site_id' => $siteId,
            'action' => $action,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }

    public function getRewardClickStats(int $memberRewardId): array
    {
        $clicks = RewardClick::where('member_reward_id', $memberRewardId)->get();

        return [
            'total_clicks' => $clicks->count(),
            'views' => $clicks->where('action', 'view')->count(),
            'claims' => $clicks->where('action', 'claim')->count(),
            'code_copies' => $clicks->where('action', 'copy_code')->count(),
            'unique_members' => $clicks->unique('member_id')->count()
        ];
    }

    protected function getModelClass(): string
    {
        return MemberReward::class;
    }
}