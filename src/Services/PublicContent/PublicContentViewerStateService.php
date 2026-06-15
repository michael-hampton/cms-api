<?php

namespace App\Services\PublicContent;

use App\Models\Badge;
use App\Models\Member;
use App\Models\Page;
use App\Repositories\Members\BadgeRepository;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\Members\ArticleGiftingService;
use App\Services\Members\BadgeService;

final class PublicContentViewerStateService
{
    public function __construct(
        private readonly PageLikeRepository $likes,
        private readonly PageViewRepository $views,
        private readonly ArticleAccessService $access,
        private readonly ArticleGiftingService $gifting,
        private readonly BadgeRepository $badges,
        private readonly BadgeService $badgeService,
    ) {
    }

    public function for(Page $page, int $siteId, string $siteSlug, ?Member $member): array
    {
        $authenticated = $member !== null;
        $decision = $this->access->canView($page, $member);
        $base = '/api/v1/' . rawurlencode($siteSlug) . '/content/' . $page->id;
        $gift = $member ? $this->gifting->checkAndClaimGiftForPage($member, $page) : null;

        return [
            'authenticated' => $authenticated,
            'can_view' => (bool)($decision['can_view'] ?? false),
            'access_reason' => $decision['reason'] ?? null,
            'liked' => $authenticated
                ? $this->likes->isLikedBy((int)$page->id, (int)$member->id, $siteId)
                : false,
            'like_count' => $this->likes->getLikeCount((int)$page->id),
            'view_count' => $this->views->getTotalViewsForPage((int)$page->id),
            'can_comment' => $authenticated,
            'subscription' => [
                'required' => !($decision['can_view'] ?? false),
                'reason' => $decision['reason'] ?? null,
            ],
            'gift' => $gift ? [
                'id' => (int)$gift->id,
                'claimed' => true,
                'message' => 'Gift access has been applied to this article.',
            ] : null,
            'next_comment_badge' => $member ? $this->nextCommentBadge($member, $siteId) : null,
            'actions' => [
                'like' => $base . '/like',
                'view' => $base . '/views',
                'comments' => $base . '/comments',
                'login' => '/' . rawurlencode($siteSlug) . '/member/login',
            ],
        ];
    }

    private function nextCommentBadge(Member $member, int $siteId): ?array
    {
        $earned = $this->badges->getEarnedBadges($member);

        $badges = Badge::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('category', 'engagement')
            ->get()
            ->filter(static function ($badge) {
                return collect($badge->criteria)
                    ->contains(static fn($criteria) => ($criteria['type'] ?? null) === 'comments_count');
            })
            ->sortBy(static function ($badge) {
                return collect($badge->criteria)
                    ->firstWhere('type', 'comments_count')['value'] ?? PHP_INT_MAX;
            });

        foreach ($badges as $badge) {
            if ($earned->contains('id', $badge->id)) {
                continue;
            }

            return [
                'id' => (int)$badge->id,
                'name' => (string)$badge->name,
                'description' => $badge->description ?? null,
                'progress' => $this->badgeService->calculateBadgeProgress($member, $badge),
            ];
        }

        return null;
    }
}
