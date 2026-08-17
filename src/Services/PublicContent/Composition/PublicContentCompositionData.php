<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\Sources\SourceResult;
use App\Framework\Support\Collection;
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
use App\Services\PublicContent\Badges\PublicContentBadgeModalService;
use App\Services\PublicContent\CompositionDeadline;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Deals\PublicContentDealsSource;
use App\Services\PublicContent\Images\PublicContentListingImageHydrator;
use App\Services\PublicContent\Newsletter\NewsletterWidgetStateResolver;
use App\Services\PublicContent\Recirculation\BudgetAwareRecirculationResolver;
use App\Services\PublicContent\Social\PageSocialShareStateResolver;
use App\Services\PublicContent\Subscriptions\PublicContentModalPlanPreparer;
use App\Services\PublicContent\Vouchers\PublicVoucherCarouselProvider;
use App\Services\Subscriptions\SubscriptionModalService;

final class PublicContentCompositionData
{
    public function __construct(
        private readonly PublicCategoryRepository $categories,
        private readonly PublicActivityFeedRepository $activityFeed,
        private readonly TrendingContentRepository $trending,
        private readonly PublicContentDealsSource $dealsSource,
        private readonly PublicVoucherCarouselProvider $voucherCarousel,
        private readonly PublicLandingSectionProvider $landingSections,
        private readonly PublicCommentBadgeProvider $commentBadges,
        private readonly PageLikeRepository $likes,
        private readonly PageViewRepository $views,
        private readonly ArticleGiftingService $gifting,
        private readonly SubscriptionModalService $subscriptionModal,
        private readonly PublicContentModalPlanPreparer $modalPlanPreparer,
        private readonly PublicContentBadgeModalService $badgeModals,
        private readonly BadgeAccessService $badgeAccess,
        private readonly BudgetAwareRecirculationResolver $recirculation,
        private readonly NewsletterWidgetStateResolver $newsletterState,
        private readonly PageSocialShareStateResolver $socialShare,
        private readonly PublicContentListingImageHydrator $listingImages,
        private readonly PublicContentConfigSource $publicContentConfig,
    ) {
    }

    public function build(
        Page $page,
        int $siteId,
        string $siteSlug,
        ?Member $member,
        array $links,
        ?Territory $territory = null,
        ?CompositionDeadline $deadline = null,
    ): array {
        $deadline ??= CompositionDeadline::unlimited();
        $canAccessBadges = $this->badgeAccess->canAccessBadges($member, $siteId);
        $badge = $canAccessBadges ? $this->commentBadges->next($member, $siteId) : null;
        $directoryBase = '/' . rawurlencode($siteSlug);

        if ($territory) {
            $directoryBase .= '/' . rawurlencode((string) $territory->slug);
        }

        $canonicalUrl = (string) ($links['canonical'] ?? ('/' . rawurlencode($siteSlug) . '/' . rawurlencode((string) $page->slug)));
        $newsletterState = $this->newsletterState->resolve($siteId, $siteSlug, $member);
        $socialShare = $this->socialShare->resolve($page, $canonicalUrl);
        $todaysDealsLimit = max(1, (int) $this->publicContentConfig->get($siteId, 'widgets.deals.limit', 10));
        $todaysDealsResult = $this->dealsSource->resolve($siteId, (string) $page->page_type, $todaysDealsLimit);
        $activityFeedLimit = max(1, (int) $this->publicContentConfig->get($siteId, 'widgets.activity-feed.limit', 10));
        $trendingLimit = max(1, (int) $this->publicContentConfig->get($siteId, 'widgets.trending.limit', 3));

        return [
            'categories' => $this->categories->getActiveWithPages($siteId),
            'categoriesWithPages' => $this->landingSections->for($page, $siteId),
            'feedPages' => $this->listingImages->hydrate($this->activityFeed->latestPublished($siteId, $activityFeedLimit)),
            'trendingPages' => $this->hydrateTrendingPages(
                $this->trending->getTrendingConversations($siteId, $trendingLimit),
            ),
            'todaysDealsResult' => $todaysDealsResult,
            'todaysDeals' => $todaysDealsResult->items(),
            'vouchers' => $this->voucherCarousel->forPage($page, $siteId),
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
            'recirculation' => $this->hydrateRecirculation(
                $this->recirculation->resolve(
                    $page,
                    $siteId,
                    $deadline,
                    max(1, (int) $this->publicContentConfig->get($siteId, 'widgets.recirculation.limit', 4)),
                ),
            ),
            'newsletterState' => $newsletterState,
            'socialShare' => $socialShare,
        ];
    }

    public function subscriptionModalData(?Member $member, int $siteId): array
    {
        return $this->modalPlanPreparer->prepare(
            $this->subscriptionModal->getModalData($member, $siteId),
        );
    }

    private function hydrateTrendingPages(mixed $trending): mixed
    {
        if ($trending instanceof Collection) {
            return $this->listingImages->hydrate($trending);
        }

        return $trending;
    }

    private function hydrateRecirculation(mixed $recirculation): mixed
    {
        if (!$recirculation instanceof SourceResult || !$recirculation->isOk()) {
            return $recirculation;
        }

        $items = $recirculation->items();
        if ($items === []) {
            return $recirculation;
        }

        $hydrated = $this->listingImages->hydrate(new Collection($items));

        return SourceResult::ok($hydrated->all());
    }
}
