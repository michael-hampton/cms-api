<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\DTO\PublicContent\Widgets\WidgetLayoutOverride;
use App\Enums\PublicContent\WidgetRegion;
use App\Framework\Database\Database;
use App\Models\Page;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Widgets\PageWidgetOverrideService;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;
use App\Services\PublicContent\Widgets\WidgetRegionNormaliser;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PageWidgetOverrideServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_lists_overrides_for_a_site_owned_page(): void
    {
        $override = new WidgetLayoutOverride('comments', WidgetRegion::Sidebar, 20, true);
        $service = $this->service(
            pages: $this->pages(true),
            widgets: $this->widgets([
                'getForPage' => [$override],
            ]),
        );

        $result = $service->listForPage(7, 42);

        self::assertSame([$override], $result);
    }

    public function test_it_rejects_a_page_from_another_site(): void
    {
        $service = $this->service(pages: $this->pages(false));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Content not found.');

        $service->listForPage(7, 42);
    }

    public function test_it_syncs_overrides_inside_a_transaction(): void
    {
        $saved = [
            new WidgetLayoutOverride('comments', WidgetRegion::Sidebar, 20, true),
        ];
        $transactionCalled = false;
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) use (&$transactionCalled) {
                $transactionCalled = true;

                return $callback();
            });

        $widgets = Mockery::mock(PageWidgetRepositoryInterface::class);
        $widgets->shouldReceive('deleteForPage')->once()->with(7, 42);
        $widgets->shouldReceive('upsert')->once()->with(
            7,
            42,
            Mockery::on(static fn(WidgetLayoutOverride $override): bool =>
                $override->widgetKey === 'comments'
                && $override->region === WidgetRegion::Sidebar
                && $override->priority === 20
                && $override->enabled === true
            ),
        );
        $widgets->shouldReceive('getForPage')->once()->with(7, 42)->andReturn($saved);

        $service = $this->service(
            pages: $this->pages(true),
            widgets: $widgets,
            database: $database,
        );

        $result = $service->syncForPage(7, 42, [
            ['widget_key' => 'comments', 'region' => 'sidebar', 'priority' => 20, 'is_enabled' => true],
        ]);

        self::assertTrue($transactionCalled);
        self::assertSame($saved, $result);
    }

    public function test_it_rejects_unknown_widget_keys(): void
    {
        $service = $this->service(pages: $this->pages(true));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown widget key at index 0.');

        $service->syncForPage(7, 42, [
            ['widget_key' => 'not-a-widget', 'region' => 'sidebar'],
        ]);
    }

    public function test_it_rejects_unknown_regions(): void
    {
        $service = $this->service(pages: $this->pages(true));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown widget region at index 0.');

        $service->syncForPage(7, 42, [
            ['widget_key' => 'comments', 'region' => 'footer-rail'],
        ]);
    }

    public function test_it_rejects_non_numeric_priority(): void
    {
        $service = $this->service(pages: $this->pages(true));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Widget priority at index 0 must be numeric.');

        $service->syncForPage(7, 42, [
            ['widget_key' => 'comments', 'region' => 'sidebar', 'priority' => 'high'],
        ]);
    }

    public function test_an_empty_list_clears_overrides_inside_a_transaction(): void
    {
        $transactionCalled = false;
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $callback) use (&$transactionCalled) {
                $transactionCalled = true;

                return $callback();
            });

        $widgets = Mockery::mock(PageWidgetRepositoryInterface::class);
        $widgets->shouldReceive('deleteForPage')->once()->with(7, 42);
        $widgets->shouldReceive('upsert')->never();
        $widgets->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([]);

        $service = $this->service(
            pages: $this->pages(true),
            widgets: $widgets,
            database: $database,
        );

        $result = $service->syncForPage(7, 42, []);

        self::assertTrue($transactionCalled);
        self::assertSame([], $result);
    }

    public function test_it_canonicalises_top_to_header_when_saving(): void
    {
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('transaction')->once()->andReturnUsing(fn(callable $callback) => $callback());

        $captured = null;
        $widgets = Mockery::mock(PageWidgetRepositoryInterface::class);
        $widgets->shouldReceive('deleteForPage')->once();
        $widgets->shouldReceive('upsert')->once()->with(
            7,
            42,
            Mockery::on(static function (WidgetLayoutOverride $override) use (&$captured): bool {
                $captured = $override;

                return $override->region === WidgetRegion::Header;
            }),
        );
        $widgets->shouldReceive('getForPage')->once()->andReturn([]);

        $service = $this->service(pages: $this->pages(true), widgets: $widgets, database: $database);

        $service->syncForPage(7, 42, [
            ['widget_key' => 'comments', 'region' => 'top'],
        ]);

        self::assertInstanceOf(WidgetLayoutOverride::class, $captured);
        self::assertSame(WidgetRegion::Header, $captured->region);
    }

    /**
     * @param array{getForPage?: list<WidgetLayoutOverride>} $behaviour
     */
    private function widgets(array $behaviour = []): PageWidgetRepositoryInterface
    {
        $widgets = Mockery::mock(PageWidgetRepositoryInterface::class);
        $widgets->shouldReceive('getForPage')->andReturn($behaviour['getForPage'] ?? [])->byDefault();
        $widgets->shouldReceive('deleteForPage')->byDefault();
        $widgets->shouldReceive('upsert')->byDefault();

        return $widgets;
    }

    private function pages(bool $found): PublicContentPageRepository
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 42;
        $page->site_id = 7;

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findPreviewById')->with(42, 7)->andReturn($found ? $page : null);

        return $pages;
    }

    private function service(
        ?PublicContentPageRepository $pages = null,
        ?PageWidgetRepositoryInterface $widgets = null,
        ?Database $database = null,
    ): PageWidgetOverrideService {
        $registry = new PublicContentWidgetRegistry([
            $this->definition('comments'),
        ]);

        return new PageWidgetOverrideService(
            $widgets ?? $this->widgets(),
            $pages ?? $this->pages(true),
            $registry,
            new WidgetRegionNormaliser(),
            $database ?? Mockery::mock(Database::class),
        );
    }

    private function definition(string $key): \App\Services\PublicContent\Widgets\PublicContentWidgetDefinition
    {
        return new class($key) implements \App\Services\PublicContent\Widgets\PublicContentWidgetDefinition {
            public function __construct(private readonly string $key)
            {
            }

            public function key(): string
            {
                return $this->key;
            }

            public function defaultPlacement(): \App\Services\PublicContent\Widgets\WidgetPlacement
            {
                return new \App\Services\PublicContent\Widgets\WidgetPlacement($this->key, 'after-content', 100);
            }

            public function supports(\App\DTO\PublicContent\PublicContentContext $context): bool
            {
                return true;
            }

            public function build(
                \App\DTO\PublicContent\PublicContentContext $context,
                \App\Services\PublicContent\Widgets\WidgetPlacement $placement,
            ): \App\DTO\PublicContent\PublicContentComponent {
                throw new \LogicException('Not needed by this test.');
            }
        };
    }
}
