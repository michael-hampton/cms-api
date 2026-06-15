<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;

final class RegionalPublicContentComponentFactory
{
    public function __construct(private readonly ViewRenderer $views)
    {
    }

    public function make(PublicContentContext $context): ?PublicContentComponent
    {
        if (empty($context->viewData['territory'])) {
            return null;
        }

        return new PublicContentComponent(
            id: 'region-context',
            type: 'region-context',
            region: 'after-content',
            priority: 90,
            html: $this->views->partial(
                'public-content-v2/components/region-context',
                $context->with([
                    'territory' => $context->viewData['territory'],
                    'allTerritories' => $context->viewData['allTerritories'] ?? [],
                    'pageGridHtml' => $context->viewData['pageGridHtml'] ?? null,
                    'regionArticles' => $context->viewData['regionArticles'] ?? [],
                ]),
            ),
            styles: [],
            scripts: [],
            endpoints: [],
            stateful: false,
        );
    }
}
