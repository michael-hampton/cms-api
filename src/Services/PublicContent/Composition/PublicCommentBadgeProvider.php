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

        foreach ($this->badges->getActiveEngagementBadges($siteId) as $badge) {
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
}
