<?php

namespace App\Repositories\Rewards;

use App\Models\RewardDefinition;
use App\Repositories\Repository;

class RewardDefinitionRepository extends Repository
{

    public function findRewardDefinitionById(int $id): ?RewardDefinition
    {
        return RewardDefinition::with(['memberRewards'])->find($id);
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
        $rewards = RewardDefinition::where('site_id', $siteId)->get();

        $counts = $rewards
            ->groupBy('reward_type')
            ->map(fn($group) => $group->count())
            ->toArray();

        return [
            'total' => RewardDefinition::where('site_id', $siteId)->count(),
            'active' => RewardDefinition::where('site_id', $siteId)->where('is_active', true)->count(),
            'inactive' => RewardDefinition::where('site_id', $siteId)->where('is_active', false)->count(),
            'by_type' => $counts
        ];
    }

    protected function getModelClass(): string
    {
        return RewardDefinition::class;
    }
}