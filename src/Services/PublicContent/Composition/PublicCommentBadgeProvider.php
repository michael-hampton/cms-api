<?php

namespace App\Services\PublicContent\Composition;

use App\Models\Member;
use App\Repositories\Members\BadgeRepository;
use App\Repositories\PublicContent\PublicBadgeRepository;
use App\Services\Members\BadgeService;

final class PublicCommentBadgeProvider
{
    public function __construct(
        private readonly PublicBadgeRepository $badges,
        private readonly BadgeRepository $earnedBadges,
        private readonly BadgeService $badgeService,
    ) {
    }

    public function next(Member $member, int $siteId): ?array
    {
        $earned = $this->earnedBadges->getEarnedBadges($member);

        $commentingBadges = $this->badges->getActiveEngagementBadges($siteId)
            ->filter(fn($badge): bool => $this->commentThreshold($badge) !== null)
            ->sortBy(fn($badge): int => $this->commentThreshold($badge) ?? PHP_INT_MAX);

        foreach ($commentingBadges as $badge) {
            if ($earned->contains('id', $badge->id)) {
                continue;
            }

            return [
                'badge' => $badge,
                'progress' => $this->badgeService->calculateBadgeProgress($member, $badge),
            ];
        }

        return null;
    }

    private function commentThreshold($badge): ?int
    {
        foreach ((array)$badge->criteria as $criteria) {
            if (($criteria['type'] ?? null) === 'comments_count') {
                return (int)($criteria['value'] ?? 0);
            }
        }

        return null;
    }
}
