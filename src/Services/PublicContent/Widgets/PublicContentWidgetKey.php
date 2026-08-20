<?php

namespace App\Services\PublicContent\Widgets;

/**
 * Maps editor / stored labels onto catalog widget keys.
 */
final class PublicContentWidgetKey
{
    public static function canonical(string $widgetKey): string
    {
        return match ($widgetKey) {
            'deals-carousel' => 'deals',
            'activity-feed-widget' => 'activity-feed',
            default => $widgetKey,
        };
    }
}
