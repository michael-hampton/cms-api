<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\Widgets\WidgetTheme;
use App\Services\PublicContent\Widgets\Contracts\WidgetThemeResolverInterface;

/**
 * Merges the site widget theme into view data so every widget can consume tokens.
 */
final class WidgetThemeViewData
{
    public function __construct(
        private readonly WidgetThemeResolverInterface $themes,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function merge(PublicContentContext $context, array $data): array
    {
        $theme = $this->themes->forSite($context->siteId);

        return array_merge($data, $this->viewPayload($theme));
    }

    /**
     * @return array{widgetTheme: WidgetTheme, designTokens: array<string, mixed>, cssVariables: array<string, string>}
     */
    public function viewPayload(WidgetTheme $theme): array
    {
        return [
            'widgetTheme' => $theme,
            'designTokens' => $theme->tokens,
            'cssVariables' => $theme->cssVariables,
        ];
    }
}
