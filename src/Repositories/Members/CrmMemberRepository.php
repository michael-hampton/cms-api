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
    /**
     * @param string  $search              Legacy broad search (name / email / order number).
     * @param ?string $orderNumber         Exact / partial order-number search.
     * @param ?string $lastName            Exact / partial last-name search.
     * @param ?string $postcode            Exact / partial postcode (zip) search.
     * @param ?string $email               Exact / partial email search.
     * @param ?string $phone               Exact / partial phone search.
     */
    public function searchMembers(
        int     $siteId,
        string  $search = '',
        ?string $status = null,
        ?int    $assignedAgentId = null,
        int     $perPage = 20,
        int     $page = 1,
        ?string $country = null,
        ?string $subscriptionStatus = null,
        ?string $orderNumber = null,
        ?string $lastName = null,
        ?string $postcode = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $updatedFrom = null,
        ?string $updatedTo = null,
    ): array {
        $query = Member::with(['addresses'])->where('site_id', $siteId)
            ->where('anonymous', false);

        // ── Legacy broad search ───────────────────────────────────────────────
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

        // ── Advanced field-level search ───────────────────────────────────────
        if (!empty($orderNumber)) {
            $memberIds = Order::where('site_id', $siteId)
                ->where('order_number', 'LIKE', "%{$orderNumber}%")
                ->get()
                ->pluck('user_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            // If no orders match, force zero results for this filter
            $query->whereIn('id', empty($memberIds) ? [0] : $memberIds);
        }

        if (!empty($lastName)) {
            $query->where('last_name', 'LIKE', "%{$lastName}%");
        }

        if (!empty($email)) {
            $query->where('email', 'LIKE', "%{$email}%");
        }

        if (!empty($phone)) {
            $query->where('phone', 'LIKE', "%{$phone}%");
        }

        if (!empty($postcode)) {
            $query->whereExists(
                $this->buildAddressPostcodeExistsQuery($postcode)
            );
        }

        // ── Standard filters ─────────────────────────────────────────────────
        if ($status !== null && $status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        if ($country !== null && $country !== '') {
            $query->whereExists(
                $this->buildAddressCountryExistsQuery($country)
            );
        }

        if ($subscriptionStatus !== null && $subscriptionStatus !== '') {
            $query->whereExists(
                $this->buildSubscriptionStatusExistsQuery($siteId, $subscriptionStatus)
            );
        }

        if ($assignedAgentId !== null) {
            $query->where('assigned_agent_id', $assignedAgentId);
        }

        if (!empty($dateFrom)) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }

        if (!empty($dateTo)) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        if (!empty($updatedFrom)) {
            $query->where('updated_at', '>=', $updatedFrom . ' 00:00:00');
        }

        if (!empty($updatedTo)) {
            $query->where('updated_at', '<=', $updatedTo . ' 23:59:59');
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
            'data'         => $members,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }

    private function buildSubscriptionStatusExistsQuery(
        int    $siteId,
        string $subscriptionStatus
    ): QueryBuilder {
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

    private function buildAddressPostcodeExistsQuery(string $postcode): QueryBuilder
    {
        return Address::query()->selectRaw('1')
            ->whereColumn('addresses.member_id', 'members.id')
            ->where('addresses.postcode', 'LIKE', "%{$postcode}%");
    }

    public function findForSite(int $memberId, int $siteId, array $relations = []): ?Member
    {
        return Member::where('id', $memberId)
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
            'total' => (float) ((clone $query)->sum('total') ?? 0),
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