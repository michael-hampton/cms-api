<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\Widgets\WidgetTheme;
use App\Models\Page;
use App\Services\PublicContent\Widgets\Contracts\WidgetThemeResolverInterface;
use App\Services\PublicContent\Widgets\WidgetThemeViewData;
use Mockery;
use PHPUnit\Framework\TestCase;

final class WidgetThemeViewDataTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_merges_site_tokens_into_widget_view_data(): void
    {
        $theme = new WidgetTheme(7, ['color' => ['accent' => '#991b1b']], ['--color-accent' => '#991b1b']);
        $resolver = Mockery::mock(WidgetThemeResolverInterface::class);
        $resolver->shouldReceive('forSite')->once()->with(7)->andReturn($theme);

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 42;
        $context = new PublicContentContext($page, 7, 'guitar-world');

        $merged = (new WidgetThemeViewData($resolver))->merge($context, ['title' => 'Trending']);

        self::assertSame('Trending', $merged['title']);
        self::assertSame($theme, $merged['widgetTheme']);
        self::assertSame('#991b1b', $merged['designTokens']['color']['accent']);
        self::assertSame('#991b1b', $merged['cssVariables']['--color-accent']);
    }
}
