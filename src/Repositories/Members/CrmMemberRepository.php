<?php

namespace App\Repositories\Members;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Order;
use App\Models\Subscription;
use App\Repositories\Repository;

class CrmMemberRepository extends Repository
{
    public function searchMembers(
        int     $siteId,
        string  $search = '',
        ?string $status = null,
        ?int    $assignedAgentId = null,
        int     $perPage = 20,
        int     $page = 1
    ): array
    {
        $query = Member::where('site_id', $siteId)
            ->where('anonymous', false);

        if (!empty($search)) {
            $orderMemberIds = Order::where('site_id', $siteId)
                ->where('order_number', 'LIKE', "%{$search}%")
                ->get()
                ->pluck('user_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $query->where(function ($q) use ($search, $orderMemberIds) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
                if (!empty($orderMemberIds)) {
                    $q->orWhereIn('id', $orderMemberIds);
                }
            });
        }

        if ($status !== null) {
            $query->where('is_active', $status === 'active');
        }

        if ($assignedAgentId !== null) {
            $query->where('assigned_agent_id', $assignedAgentId);
        }

        $total = (clone $query)->count();
        $offset = ($page - 1) * $perPage;

        $members = $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $members,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int)ceil($total / $perPage),
        ];
    }

    public function findForSite(int $memberId, int $siteId, array $relations = []): ?Member
    {
        return Member::where('id', $siteId === 0 ? $memberId : $memberId)
            ->where('site_id', $siteId)
            ->where('anonymous', false)
            ->first();
    }

    public function getAgents(int $siteId): Collection
    {
        return \App\Models\User::where('site_id', $siteId)
            ->orderBy('name')
            ->get();
    }

    public function getRecentSubscriptionsForMember(int $memberId, int $siteId, int $limit = 5): Collection
    {
        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRecentOrdersForMember(int $memberId, int $siteId, int $limit = 5): Collection
    {
        return Order::where('user_id', $memberId)
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getOrderSummaryForMember(int $memberId, int $siteId): array
    {
        $query = Order::where('user_id', $memberId)
            ->where('site_id', $siteId);

        return [
            'count' => (clone $query)->count(),
            'total' => (float)((clone $query)->sum('total') ?? 0),
        ];
    }

    protected function getModelClass(): string
    {
        return Member::class;
    }
}
