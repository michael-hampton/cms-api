<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;
use App\Services\PublicContent\Widgets\PageWidgetLayoutResolver;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;

final class PublicContentComposer
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly RegionalPublicContentComponentFactory $regionalComponents,
        private readonly PublicContentWidgetRegistry $registry,
        private readonly PageWidgetLayoutResolver $layouts,
    ) {
    }

    /** @return array<string, list<PublicContentComponent>> */
    public function compose(PublicContentContext $context): array
    {
        $this->registerDefaults();
        $regions = [];

        foreach ($this->layouts->resolve($context, $this->registry) as $placement) {
            $definition = $this->registry->get($placement->widgetKey);

            if (!$definition->supports($context)) {
                continue;
            }

            $component = $definition->build($context, $placement);
            if (trim($component->html) === '') {
                continue;
            }

            $regions[$component->region][] = $component;
        }

        $regional = $this->regionalComponents->make($context);
        if ($regional && trim($regional->html) !== '') {
            $regions[$regional->region][] = $regional;
        }

        foreach ($regions as &$components) {
            usort(
                $components,
                static fn(PublicContentComponent $left, PublicContentComponent $right): int =>
                    $left->priority <=> $right->priority,
            );
        }

        return $regions;
    }

    private function registerDefaults(): void
    {
        foreach ($this->definitions() as $definition) {
            $this->registry->register($definition);
        }
    }

    /** @return list<PublicContentComponentDefinition> */
    private function definitions(): array
    {
        $notLanding = static fn(PublicContentContext $context): bool =>
            (string)$context->page->page_type !== 'landing-page';
        $landing = static fn(PublicContentContext $context): bool =>
            (string)$context->page->page_type === 'landing-page';
        $hasCategories = static fn(PublicContentContext $context): bool =>
            $landing($context) && !empty($context->viewData['categories']);
        $hasProducts = static fn(PublicContentContext $context): bool =>
            $context->page->products && count($context->page->products) > 0;
        $hasDeals = static fn(PublicContentContext $context): bool =>
            !empty($context->viewData['todaysDeals']);
        $hasCategoriesWithPages = static fn(PublicContentContext $context): bool =>
            $landing($context) && !empty($context->viewData['categoriesWithPages']);
        $hasClaimedGift = static fn(PublicContentContext $context): bool =>
            !empty($context->viewData['claimedGift']);
        $hasSubscriptionModal = static fn(PublicContentContext $context): bool =>
            !empty($context->viewData['subscriptionModalData']);
        $hasMember = static fn(PublicContentContext $context): bool => $context->member !== null;

        return [
            $this->definition('claimed-gift', 'claimed-gift', 'public-content-v2/components/gift-claimed', 'notices', 5,
                supports: $hasClaimedGift,
                data: static fn(PublicContentContext $context): array => ['claimedGift' => $context->viewData['claimedGift'] ?? null]),
            $this->definition('page-title', 'page-title', 'components/page-title', 'header', 10),
            $this->definition('category-pills', 'category-pills', 'components/category-pills', 'header', 20, supports: $notLanding),
            $this->definition('tags', 'tags', 'tags', 'header', 30, supports: $notLanding),
            $this->definition('page-actions', 'page-actions', 'components/page-actions', 'header', 40,
                stateful: true,
                supports: $notLanding,
                endpoints: static fn(PublicContentContext $context): array => [
                    'viewer' => $context->viewData['links']['viewer_state'] ?? null,
                    'like' => $context->viewData['links']['like'] ?? null,
                    'view' => $context->viewData['links']['view'] ?? null,
                ]),
            $this->definition('categories-widget', 'categories-widget', 'components/categories-widget', 'after-content', 100,
                supports: $hasCategories,
                data: static fn(PublicContentContext $context): array => [
                    'categories' => $context->viewData['categories'] ?? [],
                    'layout' => 'carousel',
                ]),
            $this->definition('activity-feed', 'activity-feed-widget', 'components/activity-feed-widget', 'after-content', 110,
                supports: $landing,
                data: static fn(PublicContentContext $context): array => [
                    'feedPages' => $context->viewData['feedPages'] ?? [],
                    'siteSlug' => $context->siteSlug,
                ]),
            $this->definition('trending', 'trending-widget', 'components/trending-widget', 'after-content', 120,
                data: static fn(PublicContentContext $context): array => [
                    'trendingPages' => $context->viewData['trendingPages'] ?? [],
                    'siteSlug' => $context->siteSlug,
                ]),
            $this->definition('products', 'product-section', 'components/product-section', 'after-content', 130,
                styles: ['products.css'], scripts: ['product-interactions.js'], supports: $hasProducts),
            $this->definition('newsletter', 'newsletter-signup-widget', 'components/newsletter-signup-widget', 'after-content', 140, stateful: true),
            $this->definition('comments', 'comments', 'components/comments', 'after-content', 150,
                stateful: true,
                supports: $notLanding,
                endpoints: static fn(PublicContentContext $context): array => [
                    'list' => $context->viewData['links']['comments'] ?? null,
                    'create' => $context->viewData['links']['comments'] ?? null,
                ],
                data: static fn(PublicContentContext $context): array => [
                    'nextCommentBadge' => $context->viewData['nextCommentBadge'] ?? null,
                    'commentBadgeProgress' => $context->viewData['commentBadgeProgress'] ?? null,
                ]),
            $this->definition('links', 'social-links', 'components/links', 'after-content', 160),
            $this->definition('category-pages', 'category-pages', 'components/category-pages', 'below-content', 200,
                supports: $hasCategoriesWithPages,
                data: static fn(PublicContentContext $context): array => [
                    'categories' => $context->viewData['categoriesWithPages'] ?? [],
                    'site' => $context->siteSlug,
                ]),
            $this->definition('deals', 'deals-carousel', 'components/deals-carousel', 'below-content', 210,
                styles: ['deals-carousel.css'], scripts: ['deals-carousel.js'], supports: $hasDeals),
            $this->definition('guest-contributors', 'guest-contributors', 'components/guest-contributors', 'below-content', 220, supports: $landing),
            $this->definition('authors', 'authors', 'authors', 'below-content', 230, supports: $notLanding),
            $this->definition('subscription-modal', 'subscription-modal', 'components/subscription-modal', 'modals', 300,
                supports: $hasSubscriptionModal,
                data: static fn(PublicContentContext $context): array => [
                    'subscriptionModalData' => $context->viewData['subscriptionModalData'] ?? null,
                ]),
            $this->definition('newsletter-account-modal', 'newsletter-account-modal', 'components/newsletter-account-creation-modal', 'modals', 310),
            $this->definition('newsletter-modal', 'newsletter-modal', 'components/newsletter-modal', 'modals', 320),
            $this->definition('comment-modal', 'comment-modal', 'components/comment-modal', 'modals', 330),
            $this->definition('badge-earned-modal', 'badge-earned-modal', 'components/badge-earned-modal', 'modals', 340, supports: $hasMember),
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
