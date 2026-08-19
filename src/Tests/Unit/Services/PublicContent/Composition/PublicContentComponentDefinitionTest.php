<?php

namespace App\Tests\Unit\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\Widgets\WidgetTheme;
use App\Framework\Support\Collection;
use App\Framework\View\ViewRenderer;
use App\Models\Page;
use App\Services\PublicContent\Composition\PublicContentComponentDefinition;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Widgets\Contracts\WidgetThemeResolverInterface;
use App\Services\PublicContent\Widgets\WidgetPlacement;
use App\Services\PublicContent\Widgets\WidgetThemeViewData;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentComponentDefinitionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_key_returns_the_widget_id(): void
    {
        $definition = $this->definition(id: 'trending-widget');

        self::assertSame('trending-widget', $definition->key());
    }

    public function test_default_placement_uses_id_region_and_priority(): void
    {
        $definition = $this->definition(id: 'trending-widget', region: 'after-content', priority: 40);

        $placement = $definition->defaultPlacement();

        self::assertSame('trending-widget', $placement->widgetKey);
        self::assertSame('after-content', $placement->regionName());
        self::assertSame(40, $placement->priority);
    }

    public function test_supports_is_true_when_page_type_is_wildcard(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->with(1, 'widgets.trending-widget.page_types', ['*'])
            ->andReturn(['*']);

        $definition = $this->definition(id: 'trending-widget', config: $config);

        self::assertTrue($definition->supports($this->context('article')));
    }

    public function test_supports_is_false_when_page_type_not_allowed(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->with(1, 'widgets.trending-widget.page_types', ['*'])
            ->andReturn(['landing-page']);

        $definition = $this->definition(id: 'trending-widget', config: $config);

        self::assertFalse($definition->supports($this->context('article')));
    }

    public function test_supports_page_type_when_the_page_overrides_article_type(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->never();

        $definition = $this->definition(id: 'trending-widget', config: $config);
        $context = $this->context('article')->withPageTypeOverrideKeys(['trending-widget']);

        self::assertTrue($definition->supports($context));
    }

    public function test_supports_defers_to_the_custom_predicate_when_page_type_allowed(): void
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->with(1, 'widgets.trending-widget.page_types', ['*'])
            ->andReturn(['*']);

        $definition = $this->definition(
            id: 'trending-widget',
            config: $config,
            supports: static fn(PublicContentContext $context): bool => $context->siteSlug === 'allowed-site',
        );

        self::assertFalse($definition->supports($this->context('article', siteSlug: 'other-site')));
        self::assertTrue($definition->supports($this->context('article', siteSlug: 'allowed-site')));
    }

    public function test_build_renders_html_and_maps_asset_paths(): void
    {
        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')
            ->once()
            ->with('widgets/trending', Mockery::on(static function (array $data): bool {
                return isset($data['designTokens'], $data['cssVariables'], $data['widgetTheme']);
            }))
            ->andReturn('<div>trending</div>');

        $config = Mockery::mock(PublicContentConfigSource::class);

        $definition = $this->definition(
            id: 'trending-widget',
            type: 'trending',
            template: 'widgets/trending',
            region: 'after-content',
            priority: 10,
            styles: ['trending.css'],
            scripts: ['trending.js'],
            config: $config,
            views: $views,
        );

        $component = $definition->build($this->context('article'));

        self::assertInstanceOf(PublicContentComponent::class, $component);
        self::assertSame('trending-widget', $component->id);
        self::assertSame('trending', $component->type);
        self::assertSame('after-content', $component->region);
        self::assertSame(10, $component->priority);
        self::assertSame('<div>trending</div>', $component->html);
        self::assertSame(['/public/css/trending.css'], $component->styles);
        self::assertSame(['/public/js/trending.js'], $component->scripts);
    }

    public function test_build_deduplicates_scripts(): void
    {
        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')->andReturn('<div></div>');

        $definition = $this->definition(
            id: 'trending-widget',
            scripts: ['shared.js', 'shared.js', 'extra.js'],
            views: $views,
        );

        $component = $definition->build($this->context('article'));

        self::assertSame(
            ['/public/js/shared.js', '/public/js/extra.js'],
            $component->scripts,
        );
    }

    public function test_build_omits_styles_and_scripts_for_deals_carousel(): void
    {
        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')->andReturn('<div></div>');

        $definition = $this->definition(
            id: 'deals',
            type: 'deals-carousel',
            styles: ['deals.css'],
            scripts: ['deals.js'],
            views: $views,
        );

        $component = $definition->build($this->context('article'));

        self::assertSame([], $component->styles);
        self::assertSame([], $component->scripts);
    }

    public function test_build_uses_a_placement_override_when_provided(): void
    {
        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')->andReturn('<div></div>');

        $definition = $this->definition(id: 'trending-widget', region: 'after-content', priority: 10, views: $views);

        $override = new WidgetPlacement(widgetKey: 'trending-widget', region: 'below-content', priority: 99);
        $component = $definition->build($this->context('article'), $override);

        self::assertSame('below-content', $component->region);
        self::assertSame(99, $component->priority);
    }

    public function test_build_marks_load_hydration_when_stateful_and_no_explicit_strategy(): void
    {
        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')->andReturn('<div></div>');

        $definition = $this->definition(id: 'trending-widget', stateful: true, views: $views);

        $component = $definition->build($this->context('article'));

        self::assertTrue($component->stateful);
        self::assertSame(PublicContentComponent::HYDRATION_LOAD, $component->hydration);
    }

    private function definition(
        string $id = 'widget',
        string $type = 'widget',
        string $template = 'widgets/widget',
        string $region = 'after-content',
        int $priority = 10,
        array $styles = [],
        array $scripts = [],
        ?PublicContentConfigSource $config = null,
        ?ViewRenderer $views = null,
        bool $stateful = false,
        mixed $supports = null,
    ): PublicContentComponentDefinition {
        return new PublicContentComponentDefinition(
            $views ?? Mockery::mock(ViewRenderer::class)->shouldIgnoreMissing(),
            $config ?? Mockery::mock(PublicContentConfigSource::class)->shouldIgnoreMissing(['*']),
            $this->themeView(),
            $id,
            $type,
            $template,
            $region,
            $priority,
            $styles,
            $scripts,
            null,
            $stateful,
            null,
            $supports,
        );
    }

    private function themeView(): WidgetThemeViewData
    {
        $resolver = Mockery::mock(WidgetThemeResolverInterface::class);
        $resolver->shouldReceive('forSite')->andReturn(WidgetTheme::empty(1));

        return new WidgetThemeViewData($resolver);
    }

    private function context(string $pageType, string $siteSlug = 'estate'): PublicContentContext
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->page_type = $pageType;
        $page->products = new Collection();

        return new PublicContentContext(
            page: $page,
            siteId: 1,
            siteSlug: $siteSlug,
        );
    }
}