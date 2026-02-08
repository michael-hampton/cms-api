<?php

namespace App\Repositories\Rewards;

use App\Framework\Support\Collection;
use App\Models\MemberReward;
use App\Models\Model;
use App\Models\RewardClick;
use App\Models\RewardDefinition;
use App\Models\RewardVoucherCode;
use App\Repositories\Repository;

class RewardsRepository extends Repository
{
    public function __construct(
        private readonly RewardDefinitionRepository $rewardDefinitionRepository,
        private readonly RewardAuditLogRepository   $auditLogRepository
    )
    {
        parent::__construct();
    }

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

    public function createMemberReward(array $data): Model
    {
        $data['earned_at'] = $data['earned_at'] ?? now_datetime();
        $data['status'] = $data['status'] ?? 'pending';

        $reward = MemberReward::create($data);

        // Log the creation
        $this->auditLogRepository->logAction(
            memberRewardId: $reward->id,
            action: 'created',
            userId: null,
            newStatus: $reward->status,
            newData: $reward->toArray(),
            rewardDefinitionId: $reward->reward_definition_id
        );

        return $reward;
    }

    public function updateMemberReward(int $rewardId, array $data, ?int $userId = null): ?Model
    {
        $reward = $this->findMemberRewardById($rewardId);

        if (!$reward) {
            return null;
        }

        $oldData = $reward->toArray();
        $oldStatus = $reward->status;

        $reward->update($data);
        $reward = $reward->fresh();

        // Log the update
        $this->auditLogRepository->logAction(
            memberRewardId: $reward->id,
            action: 'updated',
            userId: $userId,
            oldStatus: $oldStatus,
            newStatus: $reward->status,
            oldData: $oldData,
            newData: $reward->toArray(),
            rewardDefinitionId: $reward->reward_definition_id
        );

        return $reward;
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
            ->where('expires_at', '<', now_datetime()->format('Y-m-d H:i:s'))
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
        $topLevelStats = [
            'total' => MemberReward::where('site_id', $siteId)->count(),
            'pending' => MemberReward::where('site_id', $siteId)->where('status', 'pending')->count(),
            'claimed' => MemberReward::where('site_id', $siteId)->where('status', 'claimed')->count(),
            'expired' => MemberReward::where('site_id', $siteId)->where('status', 'expired')->count(),
            'declined' => MemberReward::where('site_id', $siteId)->where('status', 'declined')->count()
        ];

        return array_merge($topLevelStats, $this->getRewardClickStats($siteId));
    }

    public function trackClick(
        int     $rewardId,
        ?int    $memberId,
        int     $siteId,
        string  $action,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array   $metadata = []  // ADD THIS
    ): void
    {
        RewardClick::create([
            'member_reward_id' => $rewardId,
            'member_id' => $memberId,
            'site_id' => $siteId,
            'action' => $action,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'channel' => $metadata['channel'] ?? null,
            'surface_type' => $metadata['surface_type'] ?? null,
            'surface_id' => $metadata['surface_id'] ?? null,
            'deal_id' => $metadata['deal_id'] ?? null,
            'clicked_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function getRewardClickStats(int $memberRewardId): array
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

    public function getRewardDefinitionStats(int $siteId): array
    {
        // Get all reward definitions for this site
        $definitions = $this->rewardDefinitionRepository->findBySite($siteId);
        $definitionIds = $definitions->pluck('id')->toArray();

        // Get all member rewards
        $memberRewards = $this->database
            ->table('member_rewards')
            ->whereIn('reward_definition_id', $definitionIds)
            ->get();

        $totalRewards = $memberRewards->count();
        $claimedRewards = $memberRewards->where('status', 'claimed')->count();
        $pendingRewards = $memberRewards->where('status', 'pending')->count();
        $expiredRewards = $memberRewards->where('status', 'expired')->count();
        $declinedRewards = $memberRewards->where('status', 'declined')->count();

        // Get click statistics
        $rewardIds = $memberRewards->pluck('id')->toArray();

        $clickStats = $this->getClickStatistics($rewardIds);

        $clickThroughRate = $totalRewards > 0
            ? round(($clickStats['unique_clickers'] / $totalRewards) * 100, 2)
            : 0;

        return [
            'total_definitions' => count($definitionIds),
            'total' => $totalRewards,
            'claimed' => $claimedRewards,
            'pending' => $pendingRewards,
            'expired' => $expiredRewards,
            'declined' => $declinedRewards,
            'claim_rate' => $totalRewards > 0 ? round(($claimedRewards / $totalRewards) * 100, 2) : 0,
            'total_clicks' => $clickStats['total_clicks'],
            'unique_clickers' => $clickStats['unique_clickers'],
            'click_through_rate' => $clickThroughRate,
            'clicks_by_action' => $clickStats['clicks_by_action'],
            'recent_clicks' => $clickStats['recent_clicks']
        ];
    }

    private function getClickStatistics(array $rewardIds): array
    {
        if (empty($rewardIds)) {
            return [
                'total_clicks' => 0,
                'unique_clickers' => 0,
                'clicks_by_action' => [],
                'recent_clicks' => []
            ];
        }

        $totalClicks = $this->database
            ->table('reward_clicks')
            ->whereIn('member_reward_id', $rewardIds)
            ->count();

        $uniqueClickers = $this->database
            ->table('reward_clicks')
            ->whereIn('member_reward_id', $rewardIds)
            ->distinct('member_id')
            ->count('member_id');

        $clicksByAction = $this->database
            ->table('reward_clicks')
            ->whereIn('member_reward_id', $rewardIds)
            ->select('action')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('action')
            ->get()
            ->pluck('count', 'action')
            ->toArray();

        $recentClicks = $this->database
            ->table('reward_clicks as rc')
            ->join('member_rewards as mr', 'rc.member_reward_id', '=', 'mr.id')
            ->join('members as m', 'rc.member_id', '=', 'm.id')
            ->whereIn('rc.member_reward_id', $rewardIds)
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

        return [
            'total_clicks' => $totalClicks,
            'unique_clickers' => $uniqueClickers,
            'clicks_by_action' => $clicksByAction,
            'recent_clicks' => $recentClicks
        ];
    }

    protected function getModelClass(): string
    {
        return MemberReward::class;
    }
}