<?php

namespace App\Services\PublicContent;

use App\Models\Member;
use App\Models\Page;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Services\Cms\Pages\ArticleAccessService;

final class PublicContentViewerStateService
{
    public function __construct(
        private readonly PageLikeRepository $likes,
        private readonly PageViewRepository $views,
        private readonly ArticleAccessService $access,
    ) {
    }

    public function for(Page $page, int $siteId, ?Member $member): array
    {
        $authenticated = $member !== null;
        $decision = $this->access->canView($page, $member);
        $base = '/api/v1/' . $siteId . '/content/' . $page->id;

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
            'gift' => null,
            'next_comment_badge' => null,
            'actions' => [
                'like' => $base . '/like',
                'view' => $base . '/views',
                'comments' => $base . '/comments',
                'login' => '/member/login',
            ],
        ];
    }
}
