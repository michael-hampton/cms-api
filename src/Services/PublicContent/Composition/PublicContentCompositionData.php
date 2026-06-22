<?php

namespace App\Services\PublicContent\Composition;

use App\Models\Member;
use App\Models\Page;
use App\Models\Territory;
use App\Models\Voucher;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Repositories\PublicContent\PublicActivityFeedRepository;
use App\Repositories\PublicContent\PublicCategoryRepository;
use App\Repositories\PublicContent\PublicVoucherRepository;
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
        private readonly PublicVoucherRepository $vouchers,
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
            'vouchers' => $this->mapVouchers($this->vouchers->activeForSite($siteId, 8)),
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

    /**
     * @return list<array<string, mixed>>
     */
    private function mapVouchers(iterable $vouchers): array
    {
        $mapped = [];

        foreach ($vouchers as $voucher) {
            if (!$voucher instanceof Voucher) {
                continue;
            }

            $mapped[] = [
                'id' => (int) $voucher->id,
                'code' => (string) $voucher->code,
                'title' => (string) $voucher->name,
                'description' => $voucher->description ? (string) $voucher->description : null,
                'type' => (string) $voucher->type,
                'value' => (float) $voucher->value,
                'discount_label' => $this->discountLabel($voucher),
                'minimum_order_value' => $voucher->minimum_order_value !== null ? (float) $voucher->minimum_order_value : null,
                'maximum_discount' => $voucher->maximum_discount !== null ? (float) $voucher->maximum_discount : null,
                'expires_at' => $this->formatDate($voucher->expires_at),
                'terms_and_conditions' => $voucher->terms_and_conditions ?? null,
            ];
        }

        return $mapped;
    }

    private function discountLabel(Voucher $voucher): string
    {
        if ((string) $voucher->type === 'percentage') {
            return rtrim(rtrim(number_format((float) $voucher->value, 2), '0'), '.') . '% off';
        }

        return '£' . number_format((float) $voucher->value, 2) . ' off';
    }

    private function formatDate(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d H:i:s');
        }

        return $date ? (string) $date : null;
    }
}
