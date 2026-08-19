<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\Enums\PublicContent\WidgetRegion;
use App\Services\PublicContent\Widgets\WidgetPlacement;
use PHPUnit\Framework\TestCase;

final class WidgetPlacementTest extends TestCase
{
    public function test_it_applies_page_overrides_without_mutating_default(): void
    {
        $default = new WidgetPlacement(
            widgetKey: 'comments',
            region: 'after-content',
            priority: 150,
        );

        $resolved = $default->withOverrides(
            region: 'below-content',
            priority: 20,
            enabled: false,
            configuration: ['title' => 'Discussion'],
        );

        self::assertSame('after-content', $default->regionName());
        self::assertSame('below-content', $resolved->regionName());
        self::assertSame(20, $resolved->priority);
        self::assertFalse($resolved->enabled);
        self::assertSame('Discussion', $resolved->config('title'));
    }

    public function test_it_canonicalises_editor_aliases_onto_layout_slots(): void
    {
        $placement = new WidgetPlacement('trending', 'top', 10);

        self::assertSame(WidgetRegion::Header, $placement->region);
        self::assertSame('after-content', $placement->withOverrides('middle')->regionName());
        self::assertSame('below-content', $placement->withOverrides('bottom')->regionName());
        self::assertSame('sidebar', $placement->withOverrides('sidebar')->regionName());
    }
}
