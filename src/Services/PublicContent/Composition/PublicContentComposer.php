<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;

final class PublicContentComposer
{
    public function __construct(private readonly ViewRenderer $views)
    {
    }

    /** @return array<string, list<PublicContentComponent>> */
    public function compose(PublicContentContext $context): array
    {
        $regions = [];

        foreach ($this->definitions() as $definition) {
            if (!$definition->supports($context)) {
                continue;
            }

            $component = $definition->build($context);
            if (trim($component->html) === '') {
                continue;
            }

            $regions[$component->region][] = $component;
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

    /** @return list<PublicContentComponentDefinition> */
    private function definitions(): array
    {
        $notLanding = static fn(PublicContentContext $context): bool =>
            (string)$context->page->page_type !== 'landing-page';
        $landing = static fn(PublicContentContext $context): bool =>
            (string)$context->page->page_type === 'landing-page';
        $hasProducts = static fn(PublicContentContext $context): bool =>
            $context->page->products && count($context->page->products) > 0;
        $hasDeals = static fn(PublicContentContext $context): bool =>
            !empty($context->viewData['todaysDeals']);
        $hasCategoriesWithPages = static fn(PublicContentContext $context): bool =>
            $landing($context) && !empty($context->viewData['categoriesWithPages']);

        return [
            $this->definition('page-title', 'page-title', 'components/page-title', 'header', 10),
            $this->definition('category-pills', 'category-pills', 'components/category-pills', 'header', 20, supports: $notLanding),
            $this->definition('tags', 'tags', 'tags', 'header', 30, supports: $notLanding),
            $this->definition(
                'page-actions',
                'page-actions',
                'components/page-actions',
                'header',
                40,
                scripts: ['page-actions.js'],
                stateful: true,
                supports: $notLanding,
                endpoints: static fn(PublicContentContext $context): array => [
                    'viewer' => $context->viewData['links']['viewer_state'] ?? null,
                    'like' => $context->viewData['links']['like'] ?? null,
                    'view' => $context->viewData['links']['view'] ?? null,
                ],
            ),
            $this->definition(
                'categories-widget',
                'categories-widget',
                'components/categories-widget',
                'after-content',
                100,
                supports: $landing,
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
                supports: $hasProducts,
            ),
            $this->definition('newsletter', 'newsletter-signup-widget', 'components/newsletter-signup-widget', 'after-content', 140, stateful: true),
            $this->definition(
                'comments',
                'comments',
                'components/comments',
                'after-content',
                150,
                scripts: ['comments.js'],
                stateful: true,
                supports: $notLanding,
                endpoints: static fn(PublicContentContext $context): array => [
                    'list' => $context->viewData['links']['comments'] ?? null,
                    'create' => $context->viewData['links']['comments'] ?? null,
                ],
                data: static fn(PublicContentContext $context): array => [
                    'nextCommentBadge' => $context->viewData['nextCommentBadge'] ?? null,
                ],
            ),
            $this->definition('links', 'social-links', 'components/links', 'after-content', 160),
            $this->definition(
                'category-pages',
                'category-pages',
                'components/category-pages',
                'below-content',
                200,
                supports: $hasCategoriesWithPages,
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
                supports: $hasDeals,
            ),
            $this->definition('guest-contributors', 'guest-contributors', 'components/guest-contributors', 'below-content', 220),
            $this->definition('authors', 'authors', 'authors', 'below-content', 230, supports: $notLanding),
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
