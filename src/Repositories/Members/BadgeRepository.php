<?php

namespace App\Repositories\Members;

use App\Framework\Support\Collection;
use App\Models\Badge;
use App\Models\Member;
use App\Models\MemberActivity;
use App\Models\MemberBadge;
use App\Models\MemberPoint;
use App\Models\Order;

class BadgeRepository
{
    public function getActiveBadgesForSite(int $siteId): Collection
    {
        return Badge::where('site_id', $siteId)
            ->where('is_active', true)
            ->get();
    }

    public function findMemberBadge(int $memberId, int $badgeId): ?MemberBadge
    {
        return MemberBadge::where('member_id', $memberId)
            ->where('badge_id', $badgeId)
            ->first();
    }

    public function createMemberActivity(array $data): MemberActivity
    {
        return MemberActivity::create($data);
    }

    public function createMemberPoint(array $data): MemberPoint
    {
        return MemberPoint::create($data);
    }

    public function createMemberBadge(array $data): MemberBadge
    {
        return MemberBadge::create($data);
    }

    public function getMemberActivitiesSince(int $memberId, $startDate): Collection
    {
        $startDate = !is_string($startDate) ? $startDate->format('Y-m-d') : $startDate;

        return MemberActivity::where('member_id', $memberId)
            ->where('activity_date', '>=', $startDate)
            ->get();
    }

    public function getEarnedBadges(Member $member): Collection
    {
        return $member->badges;
    }

    public function getCommentsCount(Member $member): int
    {
        return $member->comments()->count();
    }

    public function getDistinctPagesRead(Member $member): int
    {
        return $member->pageViews()->unique('page_id')->count();
    }

    public function getLikesGivenCount(Member $member): int
    {
        return $member->pageLikes()->count();
    }

    public function getCompletedOrdersCount(int $userId): int
    {
        return Order::where('user_id', $userId)
            ->where('status', 'completed')
            ->count();
    }

    public function getTotalSpent(int $userId): float
    {
        return Order::where('user_id', $userId)
            ->where('status', 'completed')
            ->sum('total') ?? 0;
    }

    public function getActiveBadges(int $siteId): Collection
    {
        return Badge::where('site_id', $siteId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getBadgesByCategory(int $siteId, string $category): Collection
    {
        return Badge::where('site_id', $siteId)
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}