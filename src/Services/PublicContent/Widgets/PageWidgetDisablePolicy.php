<?php

namespace App\Services\PublicContent\Widgets;

use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;

/**
 * Detects per-page widget disables stored in page_widgets.
 */
final class PageWidgetDisablePolicy
{
    public function __construct(
        private readonly PageWidgetRepositoryInterface $pageWidgets,
    ) {
    }

    public function isDisabled(int $siteId, int $pageId, string $widgetKey): bool
    {
        foreach ($this->pageWidgets->getForPage($siteId, $pageId) as $override) {
            if ($override->widgetKey === $widgetKey && $override->enabled === false) {
                return true;
            }
        }

        return false;
    }
}
