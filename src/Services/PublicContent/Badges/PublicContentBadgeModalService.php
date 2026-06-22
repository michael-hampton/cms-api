<?php

namespace App\Services\PublicContent\Badges;

use App\Models\Member;
use App\Models\MemberBadge;
use App\Services\Members\BadgeAccessService;

final class PublicContentBadgeModalService
{
    public function __construct(
        private readonly BadgeAccessService $badgeAccess,
    ) {
    }

    public function pendingFor(Member $member, int $siteId): ?array
    {
        if (!$this->badgeAccess->canAccessBadges($member, $siteId)) {
            return null;
        }

        $memberBadges = MemberBadge::where('member_id', (int) $member->id)
            ->whereNull('modal_viewed_at')
            ->with(['badge'])
            ->orderByDesc('earned_at')
            ->get();

        foreach ($memberBadges as $memberBadge) {
            if (!$memberBadge->badge || (int) $memberBadge->badge->site_id !== $siteId) {
                continue;
            }

            return [
                'member_badge_id' => (int) $memberBadge->id,
                'badge_id' => (int) $memberBadge->badge->id,
                'name' => (string) $memberBadge->badge->name,
                'description' => (string) ($memberBadge->badge->description ?? ''),
                'icon' => $memberBadge->badge->icon ?? '🏆',
                'points' => (int) $memberBadge->badge->points,
                'earned_at' => $memberBadge->earned_at?->format('Y-m-d H:i:s'),
            ];
        }

        return null;
    }

    public function markViewed(int $memberBadgeId, int $memberId, int $siteId): bool
    {
        $selectedBadge = MemberBadge::where('id', $memberBadgeId)
            ->where('member_id', $memberId)
            ->with(['badge'])
            ->first();

        if (!$selectedBadge?->badge || (int) $selectedBadge->badge->site_id !== $siteId) {
            return false;
        }

        if ($selectedBadge->modal_viewed_at !== null) {
            return true;
        }

        $viewedAt = now_datetime();
        $unviewedBadges = MemberBadge::where('member_id', $memberId)
            ->whereNull('modal_viewed_at')
            ->with(['badge'])
            ->get();

        foreach ($unviewedBadges as $memberBadge) {
            if (
                $memberBadge->badge
                && (int) $memberBadge->badge->site_id === $siteId
                && $memberBadge->earned_at <= $selectedBadge->earned_at
            ) {
                $memberBadge->modal_viewed_at = $viewedAt;
                $memberBadge->save();
            }
        }

        return true;
    }
}
