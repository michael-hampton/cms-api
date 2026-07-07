<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentContext;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;

class PageWidgetLayoutResolver
{
    public function __construct(private readonly PageWidgetRepositoryInterface $repository)
    {
    }

    /** @return list<WidgetPlacement> */
    public function resolve(PublicContentContext $context, PublicContentWidgetRegistry $registry): array
    {
        $placements = [];

        foreach ($registry->all() as $definition) {
            $placement = $definition->defaultPlacement();
            $placements[$placement->widgetKey] = $placement;
        }

        foreach ($this->repository->getForPage((int)$context->page->id) as $record) {
            $key = (string)$record->widget_key;

            if (!$registry->has($key)) {
                continue;
            }

            $placements[$key] = $placements[$key]->withOverrides(
                $record->region ?: null,
                $record->priority !== null ? (int)$record->priority : null,
                $record->is_enabled !== null ? (bool)$record->is_enabled : null,
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
