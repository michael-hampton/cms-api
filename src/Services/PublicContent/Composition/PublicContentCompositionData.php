<?php

namespace App\Services\PublicContent\Composition;

use App\Models\Member;
use App\Models\Page;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Repositories\PublicContent\PublicActivityFeedRepository;
use App\Repositories\PublicContent\PublicCategoryRepository;
use App\Repositories\Recommendations\TrendingContentRepository;
use App\Services\Offers\DealsService;

final class PublicContentCompositionData
{
    public function __construct(
        private readonly PublicCategoryRepository $categories,
        private readonly PublicActivityFeedRepository $activityFeed,
        private readonly TrendingContentRepository $trending,
        private readonly DealsService $deals,
        private readonly PublicLandingSectionProvider $landingSections,
        private readonly PublicCommentBadgeProvider $commentBadges,
        private readonly PageLikeRepository $likes,
        private readonly PageViewRepository $views,
    ) {
    }

    public function build(
        Page $page,
        int $siteId,
        string $siteSlug,
        ?Member $member,
        array $links,
    ): array {
        return [
            'categories' => $this->categories->getActiveWithPages($siteId),
            'categoriesWithPages' => $this->landingSections->for($page, $siteId),
            'feedPages' => $this->activityFeed->latestPublished($siteId, 10),
            'trendingPages' => $this->trending->getTrendingConversations($siteId, 3),
            'todaysDeals' => $this->deals->getTodaysDeals(10),
            'nextCommentBadge' => $member ? $this->commentBadges->next($member, $siteId) : null,
            'comments' => $page->comments ?? [],
            'isLiked' => $member
                ? $this->likes->isLikedBy((int)$page->id, (int)$member->id, $siteId)
                : false,
            'likeCount' => $this->likes->getLikeCount((int)$page->id),
            'viewCount' => $this->views->getTotalViewsForPage((int)$page->id),
            'links' => $links,
            'siteSlug' => $siteSlug,
        ];
    }
}
