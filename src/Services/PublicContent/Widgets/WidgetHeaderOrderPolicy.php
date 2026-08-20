<?php

namespace App\Services\PublicContent\Widgets;

use App\Enums\PublicContent\WidgetRegion;

/**
 * Keeps hero-block and page-title at the top of the header region regardless of overrides.
 */
final class WidgetHeaderOrderPolicy
{
    private const int HERO_PRIORITY = 1;

    private const int PAGE_TITLE_PRIORITY = 10;

    /** @var list<string> */
    private const array PRE_TITLE_HEADER_WIDGETS = [
        'breadcrumbs',
    ];

    public function apply(WidgetPlacement $placement): WidgetPlacement
    {
        if ($placement->region !== WidgetRegion::Header || !$placement->enabled) {
            return $placement;
        }

        return match ($placement->widgetKey) {
            'hero-block' => $this->withPriority($placement, self::HERO_PRIORITY),
            'page-title' => $this->withPriority($placement, self::PAGE_TITLE_PRIORITY),
            default => $this->enforceAfterPageTitle($placement),
        };
    }

    /**
     * Dashboard-wide priorities are shared across regions. Bottom widgets with a
     * low number would otherwise render immediately after middle/after-content.
     */
    public function applyRegionFloor(WidgetPlacement $placement, ?WidgetRegion $catalogRegion = null): WidgetPlacement
    {
        if (!$placement->enabled) {
            return $placement;
        }

        $floored = $placement->region === WidgetRegion::Header
            ? $this->apply($placement)
            : $placement;

        if ($floored->pageOverride) {
            return $floored;
        }

        $catalog = ($catalogRegion ?? $floored->region)->layoutSlot();
        if (
            $floored->region === WidgetRegion::BelowContent
            && $catalog !== WidgetRegion::BelowContent
        ) {
            return $this->withPriority($floored, 900 + ($floored->priority % 100));
        }

        return $floored;
    }

    private function enforceAfterPageTitle(WidgetPlacement $placement): WidgetPlacement
    {
        if (in_array($placement->widgetKey, self::PRE_TITLE_HEADER_WIDGETS, true)) {
            return $placement;
        }

        if ($placement->priority <= self::PAGE_TITLE_PRIORITY) {
            return $this->withPriority($placement, self::PAGE_TITLE_PRIORITY + 1);
        }

        return $placement;
    }

    private function withPriority(WidgetPlacement $placement, int $priority): WidgetPlacement
    {
        return $placement->priority === $priority
            ? $placement
            : $placement->withOverrides(priority: $priority);
    }
}
