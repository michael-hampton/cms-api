<?php

namespace App\Repositories\Rewards;

use App\Framework\Support\Collection;
use App\Models\MemberReward;
use App\Models\Model;
use App\Models\RewardClick;
use App\Models\RewardDefinition;
use App\Repositories\Repository;

class RewardDefinitionRepository extends Repository
{
    public function __construct(
        private readonly RewardAuditLogRepository $auditLogRepository
    )
    {
        parent::__construct();
    }

    public function findRewardDefinitionById(int $id): ?RewardDefinition
    {
        return RewardDefinition::with(['memberRewards'])->find($id);
    }

    public function update(int $id, array $data, ?int $userId = null): ?Model
    {
        $definition = $this->findRewardDefinitionById($id);

        if (!$definition) {
            return null;
        }

        $oldData = $definition->toArray();
        $oldStatus = $definition->is_active;

        $result = parent::update($id, $data);

        if ($result) {
            $definition = $definition->fresh();

            // Log the update
            $this->auditLogRepository->logAction(
                memberRewardId: 0, // No specific member reward for definition changes
                action: 'definition_updated',
                userId: $userId,
                oldStatus: $oldStatus ? 'active' : 'inactive',
                newStatus: $definition->is_active ? 'active' : 'inactive',
                oldData: $oldData,
                newData: $definition->toArray(),
                rewardDefinitionId: $definition->id
            );
        }

        return $result;
    }

    public function create(array $data, ?int $userId = null): Model
    {
        $definition = parent::create($data);

        // Log the creation
        $this->auditLogRepository->logAction(
            memberRewardId: 0,
            action: 'definition_created',
            userId: $userId,
            newStatus: $definition->is_active ? 'active' : 'inactive',
            newData: $definition->toArray(),
            rewardDefinitionId: $definition->id
        );

        return $definition;
    }

    public function delete(int $id, ?int $userId = null): bool
    {
        $definition = $this->findRewardDefinitionById($id);

        if (!$definition) {
            return false;
        }

        $oldData = $definition->toArray();

        // Log the deletion
        $this->auditLogRepository->logAction(
            memberRewardId: 0,
            action: 'definition_deleted',
            userId: $userId,
            oldStatus: $definition->is_active ? 'active' : 'inactive',
            oldData: $oldData,
            rewardDefinitionId: $definition->id
        );

        $result = parent::delete($id);

        return $result;
    }

    public function searchRewardDefinitions(int $siteId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $query = RewardDefinition::where('site_id', $siteId);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['reward_type'])) {
            $query->where('reward_type', $filters['reward_type']);
        }

        if (!empty($filters['sort_by'])) {
            $sortOrder = $filters['sort_order'] ?? 'asc';
            $query->orderBy($filters['sort_by'], $sortOrder);
        } else {
            $query->orderBy('sort_order');
        }

        $total = $query->count();
        $definitions = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $definitions,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage)
        ];
    }

    public function getRewardDefinitionStats(int $siteId): array
    {
        $rewardDefinitions = RewardDefinition::where('site_id', $siteId)->get();
        $rewards = MemberReward::whereIn('reward_definition_id', $rewardDefinitions->pluck('id')->toArray())->get();

        $counts = $rewardDefinitions
            ->groupBy('reward_type')
            ->map(fn($group) => $group->count())
            ->toArray();

        $clickStats = $this->getClickStatistics($rewards->pluck('id')->toArray());

        $clickThroughRate = $rewards->count() > 0
            ? round(($clickStats['unique_clickers'] / $rewards->count()) * 100, 2)
            : 0;

        return [
            'total' => RewardDefinition::where('site_id', $siteId)->count(),
            'active' => RewardDefinition::where('site_id', $siteId)->where('is_active', true)->count(),
            'inactive' => RewardDefinition::where('site_id', $siteId)->where('is_active', false)->count(),
            'by_type' => $counts,
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

        $uniqueClickers = RewardClick::whereIn('member_reward_id', $rewardIds)
            ->countDistinct('member_id');

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

    public function findBySite(int $siteId): Collection
    {
        return RewardDefinition::where('site_id', $siteId)->get();
    }

    protected function getModelClass(): string
    {
        return RewardDefinition::class;
    }
}