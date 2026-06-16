<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinition;
use App\Services\PublicContent\Widgets\WidgetPlacement;

final class RegionalPublicContentComponentFactory implements PublicContentWidgetDefinition
{
    public function __construct(private readonly ViewRenderer $views)
    {
    }

    public function key(): string
    {
        return 'region-context';
    }

    public function defaultPlacement(): WidgetPlacement
    {
        return new WidgetPlacement(
            widgetKey: $this->key(),
            region: 'notices',
            priority: 1,
        );
    }

    public function supports(PublicContentContext $context): bool
    {
        return !empty($context->viewData['territory']);
    }

    public function make(PublicContentContext $context): ?PublicContentComponent
    {
        if (!$this->supports($context)) {
            return null;
        }

        return $this->build($context, $this->defaultPlacement());
    }

    public function build(
        PublicContentContext $context,
        WidgetPlacement $placement,
    ): PublicContentComponent {
        return new PublicContentComponent(
            id: $this->key(),
            type: $this->key(),
            region: $placement->region,
            priority: $placement->priority,
            html: $this->views->partial(
                'public-content-v2/components/region-context',
                $context->with([
                    'territory' => $context->viewData['territory'],
                    'allTerritories' => $context->viewData['allTerritories'] ?? [],
                    'pageGridHtml' => $context->viewData['pageGridHtml'] ?? null,
                    'regionArticles' => $context->viewData['regionArticles'] ?? [],
                    'widgetConfiguration' => $placement->configuration,
                ]),
            ),
            styles: [],
            scripts: [asset('public-content-v2-region-context.js', 'js')],
            endpoints: [],
            stateful: true,
        );
    }
}
