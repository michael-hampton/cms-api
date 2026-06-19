<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;
use App\Repositories\Members\PageViewRepository;

final class MostPopularArticlesWidget implements PublicContentWidgetDefinition
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly PageViewRepository $pageViews,
    ) {
    }

    public function key(): string
    {
        return 'most-popular-articles';
    }

    public function defaultPlacement(): WidgetPlacement
    {
        return new WidgetPlacement(
            widgetKey: $this->key(),
            region: 'main',
            priority: 30,
        );
    }

    public function supports(PublicContentContext $context): bool
    {
        return (string) $context->page->page_type === 'landing-page';
    }

    public function build(PublicContentContext $context, WidgetPlacement $placement): PublicContentComponent
    {
        $limit = max(1, (int) ($placement->configuration['limit'] ?? 6));

        return new PublicContentComponent(
            id: $this->key(),
            type: $this->key(),
            region: $placement->region,
            priority: $placement->priority,
            html: $this->views->partial('components/most-popular-articles', $context->with([
                'popularArticles' => $this->pageViews->getMostPopularArticles($context->siteId, $limit),
                'widgetConfiguration' => $placement->configuration,
            ])),
            styles: [asset('most-popular-articles.css', 'css')],
            scripts: [],
            endpoints: [],
        );
    }
}
