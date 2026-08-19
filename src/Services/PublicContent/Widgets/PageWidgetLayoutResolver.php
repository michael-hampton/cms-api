<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\Widgets\WidgetLayoutOverride;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Services\PublicContent\Widgets\Contracts\WidgetPlacementResolverInterface;

/**
 * Resolves widget region/priority/enabled with precedence:
 * catalog defaults → site article-type config → per-page page_widgets.
 */
class PageWidgetLayoutResolver implements WidgetPlacementResolverInterface
{
    public function __construct(
        private readonly PageWidgetRepositoryInterface $repository,
        private readonly WidgetSiteLayoutConfig $siteLayout,
        private readonly WidgetRegionNormaliser $regions,
    ) {
    }

    /** @return list<WidgetPlacement> */
    public function resolve(PublicContentContext $context, PublicContentWidgetRegistry $registry): array
    {
        $placements = [];
        $pageType = (string) $context->page->page_type;

        foreach ($registry->all() as $definition) {
            $placement = $definition->defaultPlacement();
            $placements[$placement->widgetKey] = $placement;
        }

        foreach ($placements as $key => $placement) {
            $site = $this->siteLayout->overlay($context->siteId, $key, $pageType);
            if ($site->isEmpty()) {
                continue;
            }

            $placements[$key] = $placement->withOverrides(
                $site->region,
                $site->priority,
            );
        }

        foreach ($this->repository->getForPage($context->siteId, (int) $context->page->id) as $override) {
            if (!$override instanceof WidgetLayoutOverride) {
                continue;
            }

            $key = $override->widgetKey;
            if (!$registry->has($key) || !isset($placements[$key])) {
                continue;
            }

            $placements[$key] = $placements[$key]->withOverrides(
                $override->region,
                $override->priority,
                $override->enabled,
                $override->configuration,
                true,
            );
        }

        $resolved = [];

        foreach ($placements as $placement) {
            if (!$placement->enabled) {
                continue;
            }

            $slot = $this->regions->toLayoutSlot($placement->region);
            $resolved[] = $placement->region === $slot
                ? $placement
                : $placement->withOverrides($slot);
        }

        return $resolved;
    }
}
