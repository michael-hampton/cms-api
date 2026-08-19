<?php

namespace App\Tests\Unit\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\Widgets\WidgetTheme;
use App\Framework\Support\Collection;
use App\Framework\View\ViewRenderer;
use App\Models\Page;
use App\Services\PublicContent\Composition\RegionalPublicContentComponentFactory;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Widgets\Contracts\WidgetThemeResolverInterface;
use App\Services\PublicContent\Widgets\WidgetPlacement;
use App\Services\PublicContent\Widgets\WidgetThemeViewData;
use Mockery;
use PHPUnit\Framework\TestCase;

final class RegionalPublicContentComponentFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_key_is_region_context(): void
    {
        self::assertSame('region-context', $this->factory()->key());
    }

    public function test_default_placement_targets_notices_region(): void
    {
        $placement = $this->factory()->defaultPlacement();

        self::assertSame('region-context', $placement->widgetKey);
        self::assertSame('notices', $placement->regionName());
        self::assertSame(1, $placement->priority);
    }

    public function test_supports_is_false_without_a_territory_in_view_data(): void
    {
        self::assertFalse($this->factory()->supports($this->context(viewData: [])));
    }

    public function test_supports_is_false_when_page_type_excluded(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->with(1, 'widgets.region-context.page_types', ['*'])
            ->andReturn(['landing-page']);

        self::assertFalse($this->factory(config: $config)->supports(
            $this->context(viewData: ['territory' => 'north'], pageType: 'article'),
        ));
    }

    public function test_supports_is_true_with_territory_and_allowed_page_type(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->with(1, 'widgets.region-context.page_types', ['*'])
            ->andReturn(['*']);

        self::assertTrue($this->factory(config: $config)->supports(
            $this->context(viewData: ['territory' => 'north'], pageType: 'article'),
        ));
    }

    public function test_make_returns_null_when_unsupported(): void
    {
        self::assertNull($this->factory()->make($this->context(viewData: [])));
    }

    public function test_make_builds_a_component_when_supported(): void
    {
        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')
            ->once()
            ->with('public-content-v2/components/region-context', Mockery::type('array'))
            ->andReturn('<div>region</div>');

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->with(1, 'widgets.region-context.page_types', ['*'])
            ->andReturn(['*']);

        $component = $this->factory($views, $config)->make($this->context(viewData: ['territory' => 'north']));

        self::assertInstanceOf(PublicContentComponent::class, $component);
        self::assertSame('region-context', $component->id);
        self::assertSame('notices', $component->region);
        self::assertSame(1, $component->priority);
        self::assertTrue($component->stateful);
        self::assertSame('<div>region</div>', $component->html);
    }

    public function test_build_uses_the_given_placement_and_view_data(): void
    {
        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')
            ->once()
            ->with('public-content-v2/components/region-context', Mockery::on(function (array $data): bool {
                return $data['territory'] === 'north'
                    && $data['allTerritories'] === ['north', 'south']
                    && $data['regionArticles'] === ['a', 'b']
                    && isset($data['designTokens']);
            }))
            ->andReturn('<div>region</div>');

        $placement = new WidgetPlacement(widgetKey: 'region-context', region: 'header', priority: 5);
        $component = $this->factory($views)->build(
            $this->context(viewData: [
                'territory' => 'north',
                'allTerritories' => ['north', 'south'],
                'regionArticles' => ['a', 'b'],
            ]),
            $placement,
        );

        self::assertSame('header', $component->region);
        self::assertSame(5, $component->priority);
    }

    private function factory(
        ?ViewRenderer $views = null,
        ?PublicContentConfigSource $config = null,
    ): RegionalPublicContentComponentFactory {
        return new RegionalPublicContentComponentFactory(
            $views ?? Mockery::mock(ViewRenderer::class),
            $config ?? Mockery::mock(PublicContentConfigSource::class)->shouldIgnoreMissing(['*']),
            $this->themeView(),
        );
    }

    private function themeView(): WidgetThemeViewData
    {
        $resolver = Mockery::mock(WidgetThemeResolverInterface::class);
        $resolver->shouldReceive('forSite')->andReturn(WidgetTheme::empty(1));

        return new WidgetThemeViewData($resolver);
    }

    private function context(array $viewData, string $pageType = 'article'): PublicContentContext
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->page_type = $pageType;
        $page->products = new Collection();

        return new PublicContentContext(
            page: $page,
            siteId: 1,
            siteSlug: 'estate',
            viewData: $viewData,
        );
    }
}
