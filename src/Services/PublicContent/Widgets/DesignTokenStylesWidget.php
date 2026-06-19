<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;
use App\Services\PublicContent\Theming\PublicContentDesignTokenProvider;

final class DesignTokenStylesWidget implements PublicContentWidgetDefinition
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly PublicContentDesignTokenProvider $designTokens,
        private readonly PublicContentWidgetEligibility $eligibility,
    ) {
    }

    public function key(): string
    {
        return 'design-tokens';
    }

    public function defaultPlacement(): WidgetPlacement
    {
        return new WidgetPlacement($this->key(), 'header', 0);
    }

    public function supports(PublicContentContext $context): bool
    {
        return $this->eligibility->supportsWidget($context, $this->key());
    }

    public function build(PublicContentContext $context, WidgetPlacement $placement): PublicContentComponent
    {
        return new PublicContentComponent(
            id: $this->key(),
            type: $this->key(),
            region: $placement->region,
            priority: $placement->priority,
            html: $this->views->partial('public-content-v2/components/design-token-styles', $context->with([
                'cssVariables' => $this->designTokens->cssVariablesForSite($context->siteId),
                'siteSlug' => $context->siteSlug,
            ])),
            styles: [],
            scripts: [],
            endpoints: [],
        );
    }
}
