<?php

namespace App\Services\PublicContent\Composition;

use App\Models\Member;
use App\Models\Page;
use App\Models\Territory;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Repositories\PublicContent\PublicActivityFeedRepository;
use App\Repositories\PublicContent\PublicCategoryRepository;
use App\Repositories\Recommendations\TrendingContentRepository;
use App\Services\Members\ArticleGiftingService;
use App\Services\Members\BadgeAccessService;
use App\Services\Offers\DealsService;
use App\Services\PublicContent\Badges\PublicContentBadgeModalService;
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
        private readonly PublicContentBadgeModalService $badgeModals,
        private readonly BadgeAccessService $badgeAccess,
    ) {
    }

    public function build(
        Page $page,
        int $siteId,
        string $siteSlug,
        ?Member $member,
        array $links,
        ?Territory $territory = null,
    ): array {
        $canAccessBadges = $this->badgeAccess->canAccessBadges($member, $siteId);
        $badge = $canAccessBadges ? $this->commentBadges->next($member, $siteId) : null;
        $directoryBase = '/' . rawurlencode($siteSlug);

        if ($territory) {
            $directoryBase .= '/' . rawurlencode((string) $territory->slug);
        }

        return [
            'categories' => $this->categories->getActiveWithPages($siteId),
            'categoriesWithPages' => $this->landingSections->for($page, $siteId),
            'feedPages' => $this->activityFeed->latestPublished($siteId, 10),
            'trendingPages' => $this->trending->getTrendingConversations($siteId, 3),
            'todaysDeals' => $this->deals->getTodaysDeals(10),
            'nextCommentBadge' => $badge['badge'] ?? null,
            'commentBadgeProgress' => $badge['progress'] ?? null,
            'badgeModalData' => $canAccessBadges ? $this->badgeModals->pendingFor($member, $siteId) : null,
            'canAccessBadges' => $canAccessBadges,
            'badgesRequireActiveSubscription' => $this->badgeAccess->badgesRequireActiveSubscription($siteId),
            'claimedGift' => $member
                ? $this->gifting->checkAndClaimGiftForPage($member, $page)
                : null,
            'subscriptionModalData' => $this->subscriptionModalData($member, $siteId),
            'isLiked' => $member
                ? $this->likes->isLikedBy((int) $page->id, (int) $member->id, $siteId)
                : false,
            'likeCount' => $this->likes->getLikeCount((int) $page->id),
            'viewCount' => $this->views->getTotalViewsForPage((int) $page->id),
            'links' => $links,
            'siteSlug' => $siteSlug,
            'territory' => $territory,
            'directoryBase' => $directoryBase,
        ];
    }

    public function subscriptionModalData(?Member $member, int $siteId): array
    {
        return $this->subscriptionModal->getModalData($member, $siteId);
    }
}
