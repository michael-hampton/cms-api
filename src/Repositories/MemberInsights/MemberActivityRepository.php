<?php

namespace App\Repositories\MemberInsights;

use App\Framework\Support\Collection;
use App\Models\MemberActivity;
use App\Repositories\Repository;

class MemberActivityRepository extends Repository
{
    public function getMemberActivities(int $memberId, int $limit = 50): Collection
    {
        return $this->where('member_id', $memberId)
            ->orderBy('activity_date', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getActivityStats(int $memberId, int $days = 30): array
    {
        $startDate = now_datetime()->subDays($days);

        $results = $this->where('member_id', $memberId)
            ->where('activity_date', '>=', $startDate->toDateString())
            ->get();

        return [
            'total' => $results->count(),
            'by_type' => $results
                ->groupBy('activity_type')
                ->map(fn($group) => $group->count())
                ->toArray()
        ];
    }

    public function getPaginatedForMember(
        int $memberId,
        int $page,
        int $perPage
    ): array
    {
        $query = $this->where('member_id', $memberId)
            ->orderByDesc('created_at');

        $total = $query->count();

        $rows = $query
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return [
            'data' => $rows,
            'total' => $total,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    protected function getModelClass(): string
    {
        return MemberActivity::class;
    }
}