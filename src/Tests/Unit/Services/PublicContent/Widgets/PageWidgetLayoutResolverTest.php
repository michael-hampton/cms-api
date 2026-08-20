<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\Widgets\WidgetLayoutOverride;
use App\Enums\PublicContent\WidgetRegion;
use App\Models\Page;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Widgets\PageWidgetLayoutResolver;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinition;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;
use App\Services\PublicContent\Widgets\WidgetPlacement;
use App\Services\PublicContent\Widgets\WidgetHeaderOrderPolicy;
use App\Services\PublicContent\Widgets\WidgetRegionNormaliser;
use App\Services\PublicContent\Widgets\WidgetSiteLayoutConfig;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PageWidgetLayoutResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_preserves_defaults_when_page_has_no_overrides(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([]);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->andReturn(null);

        $placements = $this->resolver($repository, $config)->resolve($this->context(), $this->registry());

        self::assertCount(2, $placements);
        self::assertSame('header', $placements[0]->regionName());
        self::assertSame('after-content', $placements[1]->regionName());
    }

    public function test_it_applies_site_config_before_page_overrides(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([
            new WidgetLayoutOverride(
                widgetKey: 'title',
                region: WidgetRegion::BelowContent,
                priority: 5,
                enabled: true,
                configuration: ['variant' => 'compact'],
            ),
        ]);

        $config = $this->config([
            'widgets.title.region' => 'after-content',
            'widgets.title.priority' => 50,
        ]);

        $placements = $this->resolver($repository, $config)->resolve($this->context(), $this->registry());
        $title = $this->placementByKey($placements, 'title');

        self::assertCount(2, $placements);
        self::assertSame('below-content', $title->regionName());
        self::assertSame(5, $title->priority);
        self::assertSame('compact', $title->config('variant'));
    }

    public function test_it_applies_page_specific_disable_move_priority_and_configuration(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([
            new WidgetLayoutOverride(
                widgetKey: 'title',
                region: WidgetRegion::BelowContent,
                priority: 5,
                enabled: true,
                configuration: ['variant' => 'compact'],
            ),
            new WidgetLayoutOverride(
                widgetKey: 'comments',
                region: WidgetRegion::AfterContent,
                priority: 150,
                enabled: false,
            ),
        ]);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->andReturn(null);

        $placements = $this->resolver($repository, $config)->resolve($this->context(), $this->registry());

        self::assertCount(1, $placements);
        self::assertSame('title', $placements[0]->widgetKey);
        self::assertSame('below-content', $placements[0]->regionName());
        self::assertSame(5, $placements[0]->priority);
        self::assertSame('compact', $placements[0]->config('variant'));
    }

    public function test_site_config_alone_can_move_social_links_to_header(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([]);

        $config = $this->config([
            'widgets.social-links.region' => 'header',
            'widgets.social-links.priority' => 35,
        ]);

        $registry = new PublicContentWidgetRegistry([
            $this->definition('title', 'header', 10),
            $this->definition('social-links', 'after-content', 160),
            $this->definition('comments', 'after-content', 150),
        ]);

        $social = $this->placementByKey(
            $this->resolver($repository, $config)->resolve($this->context(), $registry),
            'social-links',
        );

        self::assertSame('header', $social->regionName());
        self::assertSame(35, $social->priority);
    }

    public function test_top_middle_bottom_aliases_canonicalise_to_existing_slots(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([]);

        $config = $this->config([
            'widgets.title.region' => 'top',
            'widgets.comments.region' => 'middle',
        ]);

        $registry = new PublicContentWidgetRegistry([
            $this->definition('title', 'header', 10),
            $this->definition('comments', 'after-content', 150),
            $this->definition('authors', 'below-content', 230),
        ]);

        $config->shouldReceive('get')->with(7, 'widgets.authors.region', null)->andReturn('bottom');
        $config->shouldReceive('get')->with(7, 'widgets.authors.priority', null)->andReturn(null);
        $config->shouldReceive('get')->with(7, 'widgets.authors.page_type_placements', [])->andReturn([]);

        $placements = $this->resolver($repository, $config)->resolve($this->context(), $registry);

        self::assertSame('header', $this->placementByKey($placements, 'title')->regionName());
        self::assertSame('after-content', $this->placementByKey($placements, 'comments')->regionName());
        self::assertSame('below-content', $this->placementByKey($placements, 'authors')->regionName());
    }

    public function test_article_type_placement_can_move_a_widget_to_the_sidebar(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([]);

        $config = $this->config([
            'widgets.comments.region' => 'bottom',
            'widgets.comments.page_type_placements' => [
                'article' => ['region' => 'sidebar', 'priority' => 20],
            ],
        ]);

        $comments = $this->placementByKey(
            $this->resolver($repository, $config)->resolve($this->context(), $this->registry()),
            'comments',
        );

        self::assertSame('sidebar', $comments->regionName());
        self::assertSame(20, $comments->priority);
    }

    public function test_page_override_beats_article_type_placement(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([
            new WidgetLayoutOverride(
                widgetKey: 'comments',
                region: WidgetRegion::Bottom,
                priority: 40,
                enabled: true,
            ),
        ]);

        $config = $this->config([
            'widgets.comments.page_type_placements' => [
                'article' => ['region' => 'sidebar', 'priority' => 20],
            ],
        ]);

        $comments = $this->placementByKey(
            $this->resolver($repository, $config)->resolve($this->context(), $this->registry()),
            'comments',
        );

        self::assertSame('below-content', $comments->regionName());
        self::assertSame(40, $comments->priority);
        self::assertTrue($comments->pageOverride);
    }

    public function test_it_pins_hero_block_and_page_title_when_page_override_moves_other_widgets(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([
            new WidgetLayoutOverride(
                widgetKey: 'comments',
                region: WidgetRegion::Header,
                priority: 2,
                enabled: true,
            ),
        ]);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->andReturn(null);

        $registry = new PublicContentWidgetRegistry([
            $this->definition('hero-block', 'header', 1),
            $this->definition('page-title', 'header', 10),
            $this->definition('comments', 'after-content', 150),
        ]);

        $placements = $this->resolver($repository, $config)->resolve($this->context(), $registry);

        self::assertSame(1, $this->placementByKey($placements, 'hero-block')->priority);
        self::assertSame(10, $this->placementByKey($placements, 'page-title')->priority);
        self::assertSame(11, $this->placementByKey($placements, 'comments')->priority);
    }

    public function test_it_maps_deals_carousel_overrides_onto_the_deals_widget(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([
            new WidgetLayoutOverride(
                widgetKey: 'deals-carousel',
                enabled: false,
            ),
        ]);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->andReturn(null);

        $registry = new PublicContentWidgetRegistry([
            $this->definition('deals', 'below-content', 210),
        ]);

        $placements = $this->resolver($repository, $config)->resolve($this->context(), $registry);

        self::assertCount(0, $placements);
    }

    public function test_bottom_region_floors_low_priorities_out_of_the_middle_stack(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([]);

        $config = $this->config([
            'widgets.activity-feed.region' => 'bottom',
            'widgets.activity-feed.priority' => 20,
        ]);

        $registry = new PublicContentWidgetRegistry([
            $this->definition('activity-feed', 'after-content', 110),
        ]);

        $feed = $this->placementByKey(
            $this->resolver($repository, $config)->resolve($this->context('landing-page'), $registry),
            'activity-feed',
        );

        self::assertSame('below-content', $feed->regionName());
        self::assertSame(920, $feed->priority);
    }

    public function test_site_config_does_not_mark_a_page_override(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([]);

        $config = $this->config([
            'widgets.comments.region' => 'sidebar',
        ]);

        $comments = $this->placementByKey(
            $this->resolver($repository, $config)->resolve($this->context(), $this->registry()),
            'comments',
        );

        self::assertSame('sidebar', $comments->regionName());
        self::assertFalse($comments->pageOverride);
    }

    /** @param list<WidgetPlacement> $placements */
    private function placementByKey(array $placements, string $key): WidgetPlacement
    {
        foreach ($placements as $placement) {
            if ($placement->widgetKey === $key) {
                return $placement;
            }
        }

        self::fail("Missing placement for {$key}");
    }

    private function resolver(
        PageWidgetRepositoryInterface $repository,
        PublicContentConfigSource $config,
    ): PageWidgetLayoutResolver {
        $regions = new WidgetRegionNormaliser();

        return new PageWidgetLayoutResolver(
            $repository,
            new WidgetSiteLayoutConfig($config, $regions),
            $regions,
            new WidgetHeaderOrderPolicy(),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function config(array $values): PublicContentConfigSource
    {
        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->andReturnUsing(
            static function (int $siteId, string $key, mixed $default = null) use ($values): mixed {
                return $values[$key] ?? $default;
            },
        );

        return $config;
    }

    private function context(string $pageType = 'article'): PublicContentContext
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 42;
        $page->page_type = $pageType;

        return new PublicContentContext($page, 7, 'guitar-world', null, []);
    }

    private function registry(): PublicContentWidgetRegistry
    {
        return new PublicContentWidgetRegistry([
            $this->definition('title', 'header', 10),
            $this->definition('comments', 'after-content', 150),
        ]);
    }

    private function definition(string $key, string $region, int $priority): PublicContentWidgetDefinition
    {
        return new class($key, $region, $priority) implements PublicContentWidgetDefinition {
            public function __construct(
                private readonly string $key,
                private readonly string $region,
                private readonly int $priority,
            ) {
            }

            public function key(): string
            {
                return $this->key;
            }

            public function defaultPlacement(): WidgetPlacement
            {
                return new WidgetPlacement($this->key, $this->region, $this->priority);
            }

            public function supports(PublicContentContext $context): bool
            {
                return true;
            }

            public function build(
                PublicContentContext $context,
                WidgetPlacement $placement,
            ): PublicContentComponent {
                throw new \LogicException('Not needed by this test.');
            }
        };
    }
}
