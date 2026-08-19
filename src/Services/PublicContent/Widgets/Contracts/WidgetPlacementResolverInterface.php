<?php

namespace App\Services\PublicContent\Widgets\Contracts;

use App\DTO\PublicContent\PublicContentContext;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;
use App\Services\PublicContent\Widgets\WidgetPlacement;

interface WidgetPlacementResolverInterface
{
    /**
     * Catalog defaults → site article-type config → per-page overrides.
     *
     * @return list<WidgetPlacement>
     */
    public function resolve(PublicContentContext $context, PublicContentWidgetRegistry $registry): array;
}
