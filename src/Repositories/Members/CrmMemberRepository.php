<?php

namespace App\Repositories\Members;

use App\Framework\Database\Database;
use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Address;
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
        int     $page = 1,
        ?string $country = null,
        ?string $subscriptionStatus = null,
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

        if ($status !== null && $status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        if ($country !== null && $country !== '') {
            $query->whereExists(
                $this->buildAddressCountryExistsQuery($country)
            );
        }

        // ── NEW: subscription_status filter ───────────────────────────────
        if ($subscriptionStatus !== null && $subscriptionStatus !== '') {
            $query->whereExists(
                $this->buildSubscriptionStatusExistsQuery(
                    $siteId,
                    $subscriptionStatus
                )
            );
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

    private function buildSubscriptionStatusExistsQuery(
        int    $siteId,
        string $subscriptionStatus
    ): QueryBuilder
    {
        return Subscription::query()
            ->selectRaw('1')
            ->whereColumn('subscriptions.member_id', 'members.id')
            ->where('subscriptions.site_id', $siteId)
            ->where('subscriptions.status', $subscriptionStatus);
    }

    private function buildAddressCountryExistsQuery(string $country): QueryBuilder
    {
        return Address::query()->selectRaw('1')
            ->whereColumn('addresses.member_id', 'members.id')
            ->where('addresses.country', $country);
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
            ->orderBy('id', 'desc')
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

    public function getDistinctCountries(int $siteId): array
    {
        return Database::table('addresses')
            ->join('members', 'members.id', '=', 'addresses.member_id')
            ->where('members.site_id', $siteId)
            ->whereNotNull('addresses.country')
            ->where('addresses.country', '!=', '')
            ->select('addresses.country')
            ->distinct()
            ->orderBy('addresses.country')
            ->get()
            ->pluck('country')
            ->all();
    }

    public function getDistinctSubscriptionStatuses(int $siteId): array
    {
        return Database::table('subscriptions')
            ->selectRaw('DISTINCT TRIM(status) as status')
            ->where('site_id', $siteId)
            ->whereNotNull('status')
            ->orderBy('status')
            ->get()
            ->pluck('status')
            ->all();
    }

    protected function getModelClass(): string
    {
        return Member::class;
    }
}
