<?php

namespace App\Services\PublicContent;

use App\Models\Member;
use App\Models\Page;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\Members\ArticleGiftingService;
use App\Services\PublicContent\Composition\PublicCommentBadgeProvider;

final class PublicContentViewerStateService
{
    public function __construct(
        private readonly PageLikeRepository $likes,
        private readonly PageViewRepository $views,
        private readonly ArticleAccessService $access,
        private readonly ArticleGiftingService $gifting,
        private readonly PublicCommentBadgeProvider $commentBadges,
    ) {
    }

    public function for(Page $page, int $siteId, string $siteSlug, ?Member $member): array
    {
        $authenticated = $member !== null;
        $decision = $this->access->canView($page, $member);
        $base = '/api/v1/' . rawurlencode($siteSlug) . '/content/' . $page->id;
        $gift = $member ? $this->gifting->checkAndClaimGiftForPage($member, $page) : null;
        $nextBadge = $member ? $this->commentBadges->next($member, $siteId) : null;

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
            'next_comment_badge' => $nextBadge ? [
                'id' => (int)$nextBadge['badge']->id,
                'name' => (string)$nextBadge['badge']->name,
                'description' => $nextBadge['badge']->description ?? null,
                'progress' => $nextBadge['progress'],
            ] : null,
            'actions' => [
                'like' => $base . '/like',
                'view' => $base . '/views',
                'comments' => $base . '/comments',
                'login' => '/' . rawurlencode($siteSlug) . '/member/login',
            ],
        ];
    }
}
