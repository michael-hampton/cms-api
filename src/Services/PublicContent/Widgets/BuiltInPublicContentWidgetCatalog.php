<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;
use App\Services\PublicContent\Composition\PublicContentComponentDefinition;

final class BuiltInPublicContentWidgetCatalog
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly PublicContentWidgetEligibility $eligibility,
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
            $this->definition('page-title', 'page-title', 'components/page-title', 'header', 10),
            $this->definition(
                'category-pills',
                'category-pills',
                'components/category-pills',
                'header',
                20,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->isEditorial($context),
            ),
            $this->definition(
                'tags',
                'tags',
                'tags',
                'header',
                30,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->isEditorial($context),
            ),
            $this->definition(
                'page-actions',
                'page-actions',
                'components/page-actions',
                'header',
                40,
                stateful: true,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->isNotLanding($context),
                endpoints: static fn(PublicContentContext $context): array => [
                    'viewer' => $context->viewData['links']['viewer_state'] ?? null,
                    'like' => $context->viewData['links']['like'] ?? null,
                    'view' => $context->viewData['links']['view'] ?? null,
                ],
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
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->hasHomepageCategories($context),
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
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->isLanding($context),
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
                'products',
                'product-section',
                'components/product-section',
                'after-content',
                130,
                styles: ['products.css'],
                scripts: ['product-interactions.js'],
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->hasProducts($context),
            ),
            $this->definition(
                'newsletter',
                'newsletter-signup-widget',
                'components/newsletter-signup-widget',
                'after-content',
                140,
                stateful: true,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->isLanding($context),
            ),
            $this->definition(
                'comments',
                'comments',
                'components/comments',
                'after-content',
                150,
                stateful: true,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->isEditorial($context),
                endpoints: static fn(PublicContentContext $context): array => [
                    'list' => $context->viewData['links']['comments'] ?? null,
                    'create' => $context->viewData['links']['comments'] ?? null,
                ],
                data: static fn(PublicContentContext $context): array => [
                    'nextCommentBadge' => $context->viewData['nextCommentBadge'] ?? null,
                    'commentBadgeProgress' => $context->viewData['commentBadgeProgress'] ?? null,
                ],
            ),
            $this->definition(
                'links',
                'social-links',
                'components/links',
                'after-content',
                160,
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
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->hasCategorySections($context),
                data: static fn(PublicContentContext $context): array => [
                    'categories' => $context->viewData['categoriesWithPages'] ?? [],
                    'site' => $context->siteSlug,
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
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->hasDeals($context),
            ),
            $this->definition(
                'guest-contributors',
                'guest-contributors',
                'components/guest-contributors',
                'below-content',
                220,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->isLanding($context),
            ),
            $this->definition(
                'authors',
                'authors',
                'authors',
                'below-content',
                230,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->hasAuthors($context),
            ),
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
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->hasSubscriptionModal($context),
                data: static fn(PublicContentContext $context): array => [
                    'subscriptionModalData' => $context->viewData['subscriptionModalData'] ?? null,
                ],
            ),
            $this->definition(
                'newsletter-account-modal',
                'newsletter-account-modal',
                'components/newsletter-account-creation-modal',
                'modals',
                310,
            ),
            $this->definition(
                'newsletter-modal',
                'newsletter-modal',
                'components/newsletter-modal',
                'modals',
                320,
            ),
            $this->definition(
                'comment-modal',
                'comment-modal',
                'components/comment-modal',
                'modals',
                330,
            ),
            $this->definition(
                'badge-earned-modal',
                'badge-earned-modal',
                'components/badge-earned-modal',
                'modals',
                340,
                supports: fn(PublicContentContext $context): bool =>
                    $this->eligibility->hasMember($context),
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
        mixed $supports = null,
        mixed $data = null,
    ): PublicContentComponentDefinition {
        return new PublicContentComponentDefinition(
            views: $this->views,
            id: $id,
            type: $type,
            template: $template,
            region: $region,
            priority: $priority,
            styles: $styles,
            scripts: $scripts,
            endpoints: $endpoints,
            stateful: $stateful,
            supports: $supports,
            data: $data,
        );
    }
}
