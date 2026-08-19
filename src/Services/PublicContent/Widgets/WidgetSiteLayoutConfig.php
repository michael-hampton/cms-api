<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\Widgets\WidgetLayoutOverride;
use App\Enums\PublicContent\PublicPageType;
use App\Services\PublicContent\Config\PublicContentConfigSource;

/**
 * Reads site-level widget placement, including per-article-type overlays.
 */
final class WidgetSiteLayoutConfig
{
    public function __construct(
        private readonly PublicContentConfigSource $publicContentConfig,
        private readonly WidgetRegionNormaliser $regions,
    ) {
    }

    public function overlay(int $siteId, string $widgetKey, string $pageType): WidgetLayoutOverride
    {
        $region = $this->regions->tryLayoutSlot(
            $this->publicContentConfig->get($siteId, "widgets.{$widgetKey}.region", null),
        );
        $priority = $this->numericPriority(
            $this->publicContentConfig->get($siteId, "widgets.{$widgetKey}.priority", null),
        );

        $pageTypeOverlay = $this->pageTypeOverlay($siteId, $widgetKey, $pageType);
        if ($pageTypeOverlay->region !== null) {
            $region = $pageTypeOverlay->region;
        }
        if ($pageTypeOverlay->priority !== null) {
            $priority = $pageTypeOverlay->priority;
        }

        return new WidgetLayoutOverride(
            widgetKey: $widgetKey,
            region: $region,
            priority: $priority,
        );
    }

    private function pageTypeOverlay(int $siteId, string $widgetKey, string $pageType): WidgetLayoutOverride
    {
        $resolvedType = PublicPageType::fromPage($pageType)?->value ?? $pageType;
        $placements = $this->publicContentConfig->get(
            $siteId,
            "widgets.{$widgetKey}.page_type_placements",
            [],
        );

        if (!is_array($placements) || !isset($placements[$resolvedType]) || !is_array($placements[$resolvedType])) {
            return WidgetLayoutOverride::none($widgetKey);
        }

        $typed = $placements[$resolvedType];

        return new WidgetLayoutOverride(
            widgetKey: $widgetKey,
            region: $this->regions->tryLayoutSlot($typed['region'] ?? null),
            priority: $this->numericPriority($typed['priority'] ?? null),
        );
    }

    private function numericPriority(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
