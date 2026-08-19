<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\Widgets\WidgetTheme;
use App\Services\PublicContent\Theming\PublicContentDesignTokenProvider;
use App\Services\PublicContent\Widgets\Contracts\WidgetThemeResolverInterface;

final class PublicContentWidgetThemeResolver implements WidgetThemeResolverInterface
{
    /** @var array<int, WidgetTheme> */
    private array $cache = [];

    public function __construct(
        private readonly PublicContentDesignTokenProvider $designTokens,
    ) {
    }

    public function forSite(int $siteId): WidgetTheme
    {
        return $this->cache[$siteId] ??= new WidgetTheme(
            siteId: $siteId,
            tokens: $this->designTokens->forSite($siteId),
            cssVariables: $this->designTokens->cssVariablesForSite($siteId),
        );
    }
}
