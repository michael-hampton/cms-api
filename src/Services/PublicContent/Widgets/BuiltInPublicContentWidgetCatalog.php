<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentContext;
use App\Enums\Pages\PageType;
use App\Framework\View\ViewRenderer;
use App\Services\PublicContent\Composition\PublicContentComponentDefinition;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Hero\PublicContentHeroDataResolver;
use App\Services\PublicContent\PageReviewDataFactory;

final class BuiltInPublicContentWidgetCatalog
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly PublicContentWidgetEligibility $eligibility,
        private readonly PublicContentHeroDataResolver $heroData,
        private readonly PageReviewDataFactory $reviewData,
        private readonly PublicContentConfigSource $publicContentConfig,
    ) {
    }

    /** @return list<PublicContentWidgetDefinition> */
    public function all(): array
    {
        return array_merge(
            $this->noticeWidgets(),
            $this->headerWidgets(),
            $this->afterContentWidgets(),
            $this->belowContentWidgets(),
            $this->modalWidgets(),
        );
    }

    /** @return list<PublicContentWidgetDefinition> */
    private function noticeWidgets(): array
    {
        return [
            $this->definition(
                id: 'claimed-gift',
                type: 'claimed-gift',
                template: 'public-content-v2/components/gift-claimed',
                region: 'notices',
                priority: 5,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->hasClaimedGift($context),
                data: static fn(PublicContentContext $context): array => [
                    'claimedGift' => $context->viewData['claimedGift'] ?? null,
                ],
            ),
        ];
    }

    /** @return list<PublicContentWidgetDefinition> */
    private function headerWidgets(): array
    {
        return [
            $this->definition('breadcrumbs', 'breadcrumbs', 'components/breadcrumbs', 'header', 5, supports: fn(PublicContentContext $context): bool => $this->eligibility->hasBreadcrumbs($context)),
            $this->definition(
                'page-title',
                'page-title',
                'components/page-title',
                'header',
                10,
                supports: fn(PublicContentContext $context): bool =>
                $this->eligibility->supportsWidget($context, 'page-title'),
                data: fn(PublicContentContext $context): array => [
                    'reviewRating' => \App\Enums\Pages\PageType::tryFrom((string) $context->page->page_type) === \App\Enums\Pages\PageType::Review
                        ? $this->reviewData->fromPage($context->page)?->only(['rating', 'maxRating'])
                        : null,
                ],
            ),
            $this->definition(
                'review-summary',
                'review-summary',
                'components/review-summary',
                'header',
                15,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->supportsWidget($context, 'review-summary')
                    && $this->reviewData->fromPage($context->page) !== null,
                data: fn(PublicContentContext $context): array =>
                    $this->reviewData->fromPage($context->page)?->toArray() ?? [],
            ),
            $this->definition('category-pills', 'category-pills', 'components/category-pills', 'header', 20, supports: fn(PublicContentContext $context): bool => $this->eligibility->supportsWidget($context, 'category-pills')),
            $this->definition('tags', 'tags', 'tags', 'header', 30, supports: fn(PublicContentContext $context): bool => $this->eligibility->supportsWidget($context, 'tags')),
            $this->definition(
                'page-actions',
                'page-actions',
                'components/page-actions',
                'header',
                40,
                endpoints: static fn(PublicContentContext $context): array => [
                    'viewer' => $context->viewData['links']['viewer_state'] ?? null,
                    'like' => $context->viewData['links']['like'] ?? null,
                    'view' => $context->viewData['links']['view'] ?? null,
                ],
                stateful: true,
                supports: fn(PublicContentContext $context): bool => $this->eligibility->supportsWidget($context, 'page-actions'),
            ),
            $this->definition(
                'social-links',
                'social-links',
                'components/links',
                'header',
                35,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->supportsWidget($context, 'social-links')
                    && !empty($context->viewData['socialShare']),
                data: static fn(PublicContentContext $context): array => [
                    'socialShare' => $context->viewData['socialShare'] ?? null,
                ],
            ),
            $this->definition(
                'hero-block',
                'hero-block',
                'components/hero-block',
                'header',
                1,
                supports: fn(PublicContentContext $context): bool =>
                    $this->heroData->resolve($context->page) !== null && $this->eligibility->supportsWidget($context, 'hero-block'),
                data: fn(PublicContentContext $context): array =>
                    $this->heroData->resolve($context->page)?->toArray() ?? [],
            ),
        ];
    }

    /** @return list<PublicContentWidgetDefinition> */
    private function afterContentWidgets(): array
    {
        return [
            $this->definition(
                'categories-widget',
                'categories-widget',
                'components/categories-widget',
                'after-content',
                100,
                scripts: ['categories-widget.js'],
                stateful: true,
                supports: fn(PublicContentContext $context): bool => $this->eligibility->hasHomepageCategories($context),
                data: static fn(PublicContentContext $context): array => [
                    'categories' => $context->viewData['categories'] ?? [],
                    'layout' => 'carousel',
                ],
            ),
            $this->definition(
                'activity-feed',
                'activity-feed-widget',
                'components/activity-feed-widget',
                'after-content',
                110,
                supports: fn(PublicContentContext $context): bool => $this->eligibility->isLanding($context),
                data: static fn(PublicContentContext $context): array => [
                    'feedPages' => $context->viewData['feedPages'] ?? [],
                    'siteSlug' => $context->siteSlug,
                ],
            ),
            $this->definition(
                'trending',
                'trending-widget',
                'components/trending-widget',
                'after-content',
                120,
                data: static fn(PublicContentContext $context): array => [
                    'trendingPages' => $context->viewData['trendingPages'] ?? [],
                    'siteSlug' => $context->siteSlug,
                ],
            ),
            $this->definition(
                'recirculation',
                'recirculation',
                'components/recirculation',
                'after-content',
                125,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->supportsWidget($context, 'recirculation'),
                data: static fn(PublicContentContext $context): array => [
                    'recirculation' => $context->viewData['recirculation'] ?? null,
                    'siteSlug' => $context->siteSlug,
                ],
            ),
            $this->definition(
                'products',
                'product-section',
                'components/product-section',
                'after-content',
                130,
                styles: ['products.css'],
                scripts: ['product-interactions.js'],
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->hasProducts($context)
                    || $this->eligibility->isBuyingGuide($context),
                data: fn(PublicContentContext $context): array => [
                    // Empty = no CMS-linked products. Degraded reserved for live source failure.
                    'productsEmpty' => $this->eligibility->isBuyingGuide($context)
                        && !$this->eligibility->hasProducts($context),
                    'productsDegraded' => false,
                    // Temporary path until a live product island source populates fully.
                    'productsSourceStub' => $this->eligibility->isBuyingGuide($context),
                ],
            ),
            $this->definition(
                'newsletter',
                'newsletter-signup-widget',
                'components/newsletter-signup-widget',
                'after-content',
                140,
                stateful: true,
                hydration: 'interaction',
                supports: fn(PublicContentContext $context): bool => $this->eligibility->isLanding($context),
                data: static fn(PublicContentContext $context): array => [
                    'newsletterState' => $context->viewData['newsletterState'] ?? null,
                    'siteId' => $context->siteId,
                    'siteSlug' => $context->siteSlug,
                ],
            ),
            $this->definition(
                'comments',
                'comments',
                'components/comments',
                'after-content',
                150,
                stateful: true,
                supports: fn(PublicContentContext $context): bool => $this->eligibility->supportsWidget($context, 'comments'),
                endpoints: static fn(PublicContentContext $context): array => [
                    'list' => $context->viewData['links']['comments'] ?? null,
                    'create' => $context->viewData['links']['comments'] ?? null,
                ],
                data: static fn(PublicContentContext $context): array => [
                    'nextCommentBadge' => $context->viewData['nextCommentBadge'] ?? null,
                    'commentBadgeProgress' => $context->viewData['commentBadgeProgress'] ?? null,
                ],
            ),
        ];
    }

    /** @return list<PublicContentWidgetDefinition> */
    private function belowContentWidgets(): array
    {
        return [
            $this->definition(
                'category-pages',
                'category-pages',
                'components/category-pages',
                'below-content',
                200,
                supports: fn(PublicContentContext $context): bool => $this->eligibility->hasCategorySections($context),
                data: static fn(PublicContentContext $context): array => [
                    'categories' => $context->viewData['categoriesWithPages'] ?? [],
                    'site' => $context->siteSlug,
                ],
            ),
            $this->definition(
                'vouchers',
                'voucher-carousel',
                'components/voucher-carousel',
                'below-content',
                205,
                styles: ['public-voucher-carousel.css'],
                scripts: ['public-voucher-carousel.js'],
                stateful: true,
                supports: fn(PublicContentContext $context): bool => $this->eligibility->hasVouchers($context),
                data: static fn(PublicContentContext $context): array => [
                    'vouchers' => $context->viewData['vouchers'] ?? [],
                ],
            ),
            $this->definition(
                'deals',
                'deals-carousel',
                'components/deals-carousel',
                'below-content',
                210,
                styles: ['deals-carousel.css'],
                scripts: ['deals-carousel.js'],
                stateful: true,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->supportsWidget($context, 'deals')
                    && isset($context->viewData['todaysDealsResult']),
                data: static fn(PublicContentContext $context): array => [
                    'todaysDeals' => $context->viewData['todaysDeals'] ?? [],
                    'todaysDealsResult' => $context->viewData['todaysDealsResult'] ?? null,
                ],
            ),
            $this->definition(
                'guest-contributors',
                'guest-contributors',
                'components/guest-contributors',
                'below-content',
                220,
                stateful: true,
                supports: fn(PublicContentContext $context): bool => $this->eligibility->isLanding($context),
            ),
            $this->definition('authors', 'authors', 'authors', 'below-content', 230, supports: fn(PublicContentContext $context): bool => $this->eligibility->hasAuthors($context)),
        ];
    }

    /** @return list<PublicContentWidgetDefinition> */
    private function modalWidgets(): array
    {
        return [
            $this->definition(
                'subscription-modal',
                'subscription-modal',
                'components/subscription-modal',
                'modals',
                300,
                supports: fn(PublicContentContext $context): bool => $this->eligibility->hasSubscriptionModal($context),
                data: static fn(PublicContentContext $context): array => [
                    'subscriptionModalData' => $context->viewData['subscriptionModalData'] ?? null,
                ],
            ),
            $this->definition('newsletter-account-modal', 'newsletter-account-modal', 'components/newsletter-account-creation-modal', 'modals', 310),
            $this->definition('newsletter-modal', 'newsletter-modal', 'components/newsletter-modal', 'modals', 320),
            $this->definition('comment-modal', 'comment-modal', 'components/comment-modal', 'modals', 330),
            $this->definition(
                'badge-earned-modal',
                'badge-earned-modal',
                'components/badge-earned-modal',
                'modals',
                340,
                supports: fn(PublicContentContext $context): bool => $this->eligibility->hasMember($context),
            ),
            $this->definition(
                'voucher-code-modal',
                'voucher-code-modal',
                'components/voucher-code-modal',
                'modals',
                350,
                supports: fn(PublicContentContext $context): bool => $this->eligibility->hasVouchers($context),
            ),
        ];
    }

    private function definition(
        string $id,
        string $type,
        string $template,
        string $region,
        int $priority,
        array $styles = [],
        array $scripts = [],
        mixed $endpoints = null,
        bool $stateful = false,
        ?string $hydration = null,
        mixed $supports = null,
        mixed $data = null,
    ): PublicContentComponentDefinition {
        return new PublicContentComponentDefinition(
            views: $this->views,
            publicContentConfig: $this->publicContentConfig,
            id: $id,
            type: $type,
            template: $template,
            region: $region,
            priority: $priority,
            styles: $styles,
            scripts: $scripts,
            endpoints: $endpoints,
            stateful: $stateful,
            hydration: $hydration,
            supports: $supports,
            data: $data,
        );
    }
}
