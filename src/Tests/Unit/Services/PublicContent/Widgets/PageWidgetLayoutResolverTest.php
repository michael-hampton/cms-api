<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\Support\Collection;
use App\Models\Page;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Widgets\PageWidgetLayoutResolver;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinition;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;
use App\Services\PublicContent\Widgets\WidgetPlacement;
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
        $repository->shouldReceive('getForPage')->once()->with(42)->andReturn(new Collection([]));

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->andReturn(null);

        $resolver = new PageWidgetLayoutResolver($repository, $config);
        $placements = $resolver->resolve($this->context(), $this->registry());

        self::assertCount(2, $placements);
        self::assertSame('header', $placements[0]->region);
        self::assertSame('after-content', $placements[1]->region);
    }

    public function test_it_applies_site_config_before_page_overrides(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(42)->andReturn(new Collection([
            (object) [
                'widget_key' => 'title',
                'region' => 'below-content',
                'priority' => 5,
                'is_enabled' => true,
                'configuration' => ['variant' => 'compact'],
            ],
        ]));

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->with(7, 'widgets.title.region', null)->andReturn('after-content');
        $config->shouldReceive('get')->with(7, 'widgets.title.priority', null)->andReturn(50);
        $config->shouldReceive('get')->with(7, 'widgets.comments.region', null)->andReturn(null);
        $config->shouldReceive('get')->with(7, 'widgets.comments.priority', null)->andReturn(null);

        $resolver = new PageWidgetLayoutResolver($repository, $config);
        $placements = $resolver->resolve($this->context(), $this->registry());

        self::assertCount(2, $placements);
        $title = $this->placementByKey($placements, 'title');
        self::assertSame('below-content', $title->region);
        self::assertSame(5, $title->priority);
        self::assertSame('compact', $title->config('variant'));
    }

    public function test_it_applies_page_specific_disable_move_priority_and_configuration(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(42)->andReturn(new Collection([
            (object) [
                'widget_key' => 'title',
                'region' => 'below-content',
                'priority' => 5,
                'is_enabled' => true,
                'configuration' => ['variant' => 'compact'],
            ],
            (object) [
                'widget_key' => 'comments',
                'region' => 'after-content',
                'priority' => 150,
                'is_enabled' => false,
                'configuration' => [],
            ],
        ]));

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->andReturn(null);

        $resolver = new PageWidgetLayoutResolver($repository, $config);
        $placements = $resolver->resolve($this->context(), $this->registry());

        self::assertCount(1, $placements);
        self::assertSame('title', $placements[0]->widgetKey);
        self::assertSame('below-content', $placements[0]->region);
        self::assertSame(5, $placements[0]->priority);
        self::assertSame('compact', $placements[0]->config('variant'));
    }

    public function test_site_config_alone_can_move_social_links_to_header(): void
    {
        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->once()->with(42)->andReturn(new Collection([]));

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->with(7, 'widgets.social-links.region', null)->andReturn('header');
        $config->shouldReceive('get')->with(7, 'widgets.social-links.priority', null)->andReturn(35);
        $config->shouldReceive('get')->with(7, 'widgets.title.region', null)->andReturn(null);
        $config->shouldReceive('get')->with(7, 'widgets.title.priority', null)->andReturn(null);
        $config->shouldReceive('get')->with(7, 'widgets.comments.region', null)->andReturn(null);
        $config->shouldReceive('get')->with(7, 'widgets.comments.priority', null)->andReturn(null);

        $registry = new PublicContentWidgetRegistry([
            $this->definition('title', 'header', 10),
            $this->definition('social-links', 'after-content', 160),
            $this->definition('comments', 'after-content', 150),
        ]);

        $resolver = new PageWidgetLayoutResolver($repository, $config);
        $placements = $resolver->resolve($this->context(), $registry);
        $social = $this->placementByKey($placements, 'social-links');

        self::assertSame('header', $social->region);
        self::assertSame(35, $social->priority);
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

    private function context(): PublicContentContext
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 42;

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
