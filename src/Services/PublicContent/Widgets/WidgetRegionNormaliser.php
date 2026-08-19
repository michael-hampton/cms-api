<?php

namespace App\Services\PublicContent\Widgets;

use App\Enums\PublicContent\WidgetRegion;

/**
 * Maps config/editor region aliases onto canonical composition slots.
 */
final class WidgetRegionNormaliser
{
    public function toLayoutSlot(string|WidgetRegion $region): WidgetRegion
    {
        $parsed = $region instanceof WidgetRegion
            ? $region
            : WidgetRegion::fromConfig($region);

        return $parsed->layoutSlot();
    }

    public function tryLayoutSlot(mixed $region): ?WidgetRegion
    {
        return WidgetRegion::tryFromConfig($region)?->layoutSlot();
    }
}
