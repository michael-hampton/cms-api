<?php

namespace App\Services\PublicContent;

use App\Models\Page;
use App\Repositories\PublicContent\PublicActivityFeedRepository;
use App\Repositories\Recommendations\TrendingContentRepository;
use App\Services\Offers\DealsService;

/**
 * @deprecated Public content is now assembled by the composition layer.
 */
final class PublicContentSupplementaryService
{
    public function __construct(
        private readonly PublicActivityFeedRepository $activityFeed,
        private readonly TrendingContentRepository $trending,
        private readonly DealsService $deals,
    ) {
    }

    public function for(Page $page, int $siteId, string $siteSlug): array
    {
        return [
            'activity_feed' => $this->activityFeed->latestPublished($siteId, 10),
            'trending' => $this->trending->getTrendingConversations($siteId, 3),
            'products' => $page->products ?? [],
            'deals' => $this->deals->getTodaysDeals(10),
            'newsletter' => ['enabled' => true],
            'site_slug' => $siteSlug,
        ];
    }
}
