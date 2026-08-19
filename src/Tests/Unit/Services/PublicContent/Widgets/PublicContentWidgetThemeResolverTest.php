<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Services\PublicContent\Theming\PublicContentDesignTokenProvider;
use App\Services\PublicContent\Theming\PublicContentDesignTokenSource;
use App\Services\PublicContent\Widgets\PublicContentWidgetThemeResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentWidgetThemeResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_wraps_site_tokens_and_caches_per_site(): void
    {
        $site = Mockery::mock(Site::class)->makePartial();
        $site->id = 7;
        $site->name = 'Guitar World';
        $site->logo = null;
        $site->logo_image_id = null;
        $site->settings = [];

        $sites = Mockery::mock(SiteRepository::class);
        $sites->shouldReceive('find')->twice()->with(7)->andReturn($site);

        $source = Mockery::mock(PublicContentDesignTokenSource::class);
        $source->shouldReceive('defaults')->twice()->andReturn([
            'color' => ['accent' => '#2563eb'],
            'brand' => ['heading_color' => '#303036'],
        ]);
        $source->shouldReceive('overrides')->twice()->andReturn([]);

        $resolver = new PublicContentWidgetThemeResolver(
            new PublicContentDesignTokenProvider($sites, $source),
        );

        $first = $resolver->forSite(7);
        $second = $resolver->forSite(7);

        self::assertSame($first, $second);
        self::assertSame(7, $first->siteId);
        self::assertSame('#2563eb', $first->tokens['color']['accent']);
        self::assertSame('Guitar World', $first->tokens['brand']['site_name']);
        self::assertSame('#2563eb', $first->cssVariables['--color-accent']);
        self::assertArrayNotHasKey('--brand-site-name', $first->cssVariables);
    }
}
