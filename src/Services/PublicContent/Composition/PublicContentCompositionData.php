<?php

namespace App\Services\PublicContent\Composition;

use App\Models\Member;
use App\Models\Page;
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
            'links' => $links,
            'siteSlug' => $siteSlug,
        ];
    }
}
