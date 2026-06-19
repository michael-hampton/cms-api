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
        private readonly PublicContentWidgetEligibility $eligibility,
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
        $defaultLimit = (int) config('public_content.widgets.most-popular-articles.limit', 6);
        $limit = max(1, (int) ($placement->configuration['limit'] ?? $defaultLimit));

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
