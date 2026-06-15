<?php

namespace App\Services\PublicContent\Composition;

use App\Models\Member;
use App\Models\Page;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Repositories\PublicContent\PublicActivityFeedRepository;
use App\Repositories\PublicContent\PublicCategoryRepository;
use App\Repositories\Recommendations\TrendingContentRepository;
use App\Services\Members\ArticleGiftingService;
use App\Services\Offers\DealsService;
use App\Services\Subscriptions\SubscriptionModalService;

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
        private readonly ArticleGiftingService $gifting,
        private readonly SubscriptionModalService $subscriptionModal,
    ) {
    }

    public function build(
        Page $page,
        int $siteId,
        string $siteSlug,
        ?Member $member,
        array $links,
    ): array {
        $badge = $member ? $this->commentBadges->next($member, $siteId) : null;

        return [
            'categories' => $this->categories->getActiveWithPages($siteId),
            'categoriesWithPages' => $this->landingSections->for($page, $siteId),
            'feedPages' => $this->activityFeed->latestPublished($siteId, 10),
            'trendingPages' => $this->trending->getTrendingConversations($siteId, 3),
            'todaysDeals' => $this->deals->getTodaysDeals(10),
            'nextCommentBadge' => $badge['badge'] ?? null,
            'commentBadgeProgress' => $badge['progress'] ?? null,
            'claimedGift' => $member
                ? $this->gifting->checkAndClaimGiftForPage($member, $page)
                : null,
            'subscriptionModalData' => $this->subscriptionModal->getModalData($member, $siteId),
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
