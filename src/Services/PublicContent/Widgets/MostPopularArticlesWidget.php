<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;
use App\Repositories\Members\PageViewRepository;
use App\Services\PublicContent\Config\PublicContentConfigSource;

final class MostPopularArticlesWidget implements PublicContentWidgetDefinition
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly PageViewRepository $pageViews,
        private readonly PublicContentWidgetEligibility $eligibility,
        private readonly PublicContentConfigSource $publicContentConfig,
        private readonly WidgetThemeViewData $themeView,
    ) {
    }

    public function key(): string
    {
        return 'most-popular-articles';
    }

    public function defaultPlacement(): WidgetPlacement
    {
        return new WidgetPlacement($this->key(), 'after-content', 105);
    }

    public function supports(PublicContentContext $context): bool
    {
        return $this->eligibility->supportsWidget($context, $this->key());
    }

    public function build(PublicContentContext $context, WidgetPlacement $placement): PublicContentComponent
    {
        $defaultLimit = (int) $this->publicContentConfig->get(
            $context->siteId,
            'widgets.most-popular-articles.limit',
            6,
        );
        $limit = max(1, (int) ($placement->configuration['limit'] ?? $defaultLimit));
        $title = (string) ($placement->configuration['title']
            ?? $this->publicContentConfig->get($context->siteId, 'widgets.most-popular-articles.title', 'Most popular'));

        return new PublicContentComponent(
            id: $this->key(),
            type: $this->key(),
            region: $placement->regionName(),
            priority: $placement->priority,
            html: $this->views->partial('components/most-popular-articles', $context->with($this->themeView->merge($context, [
                'popularArticles' => $this->pageViews->getMostPopularArticles($context->siteId, $limit),
                'popularArticlesTitle' => $title,
                'widgetConfiguration' => $placement->configuration,
            ]))),
            styles: [asset('most-popular-articles.css', 'css')],
            scripts: [],
            endpoints: [],
        );
    }
}
