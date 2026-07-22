<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentContext;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Services\PublicContent\Config\PublicContentConfigSource;

/**
 * Resolves widget region/priority/enabled with precedence:
 * catalog defaults → site public_content config → per-page page_widgets.
 */
class PageWidgetLayoutResolver
{
    public function __construct(
        private readonly PageWidgetRepositoryInterface $repository,
        private readonly PublicContentConfigSource $publicContentConfig,
    ) {
    }

    /** @return list<WidgetPlacement> */
    public function resolve(PublicContentContext $context, PublicContentWidgetRegistry $registry): array
    {
        $placements = [];

        foreach ($registry->all() as $definition) {
            $placement = $definition->defaultPlacement();
            $placements[$placement->widgetKey] = $placement;
        }

        foreach ($placements as $key => $placement) {
            $siteRegion = $this->publicContentConfig->get($context->siteId, "widgets.{$key}.region", null);
            $sitePriority = $this->publicContentConfig->get($context->siteId, "widgets.{$key}.priority", null);

            if (!is_string($siteRegion) && !is_numeric($sitePriority)) {
                continue;
            }

            $placements[$key] = $placement->withOverrides(
                is_string($siteRegion) && $siteRegion !== '' ? $siteRegion : null,
                is_numeric($sitePriority) ? (int) $sitePriority : null,
            );
        }

        foreach ($this->repository->getForPage((int) $context->page->id) as $record) {
            $key = (string) $record->widget_key;

            if (!$registry->has($key) || !isset($placements[$key])) {
                continue;
            }

            $placements[$key] = $placements[$key]->withOverrides(
                $record->region ?: null,
                $record->priority !== null ? (int) $record->priority : null,
                $record->is_enabled !== null ? (bool) $record->is_enabled : null,
                is_array($record->configuration) ? $record->configuration : [],
            );
        }

        $resolved = [];

        foreach ($placements as $placement) {
            if ($placement->enabled) {
                $resolved[] = $placement;
            }
        }

        return $resolved;
    }
}
