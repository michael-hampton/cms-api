<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

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

        self::assertSame('after-content', $default->region);
        self::assertSame('below-content', $resolved->region);
        self::assertSame(20, $resolved->priority);
        self::assertFalse($resolved->enabled);
        self::assertSame('Discussion', $resolved->config('title'));
    }
}
