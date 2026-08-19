<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\Enums\PublicContent\WidgetRegion;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Widgets\WidgetRegionNormaliser;
use App\Services\PublicContent\Widgets\WidgetSiteLayoutConfig;
use Mockery;
use PHPUnit\Framework\TestCase;

final class WidgetSiteLayoutConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_reads_site_region_and_priority(): void
    {
        $overlay = $this->config([
            'widgets.comments.region' => 'sidebar',
            'widgets.comments.priority' => '20',
        ])->overlay(7, 'comments', 'article');

        self::assertSame(WidgetRegion::Sidebar, $overlay->region);
        self::assertSame(20, $overlay->priority);
    }

    public function test_page_type_placement_beats_the_site_default(): void
    {
        $overlay = $this->config([
            'widgets.comments.region' => 'header',
            'widgets.comments.priority' => 10,
            'widgets.comments.page_type_placements' => [
                'article' => [
                    'region' => 'sidebar',
                    'priority' => 40,
                ],
            ],
        ])->overlay(7, 'comments', 'article');

        self::assertSame(WidgetRegion::Sidebar, $overlay->region);
        self::assertSame(40, $overlay->priority);
    }

    public function test_it_canonicalises_top_middle_bottom_aliases(): void
    {
        $overlay = $this->config([
            'widgets.title.region' => 'top',
            'widgets.title.page_type_placements' => [
                'review' => ['region' => 'bottom'],
            ],
        ])->overlay(7, 'title', 'review');

        self::assertSame(WidgetRegion::BelowContent, $overlay->region);
    }

    public function test_unknown_or_non_numeric_values_are_ignored(): void
    {
        $overlay = $this->config([
            'widgets.comments.region' => 'footer-rail',
            'widgets.comments.priority' => 'high',
            'widgets.comments.page_type_placements' => 'not-an-array',
        ])->overlay(7, 'comments', 'article');

        self::assertNull($overlay->region);
        self::assertNull($overlay->priority);
        self::assertTrue($overlay->isEmpty());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function config(array $values): WidgetSiteLayoutConfig
    {
        $source = Mockery::mock(PublicContentConfigSource::class);
        $source->shouldReceive('get')->andReturnUsing(
            static fn(int $siteId, string $key, mixed $default = null): mixed => $values[$key] ?? $default,
        );

        return new WidgetSiteLayoutConfig($source, new WidgetRegionNormaliser());
    }
}
