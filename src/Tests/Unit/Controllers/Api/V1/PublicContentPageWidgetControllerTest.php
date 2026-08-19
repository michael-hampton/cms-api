<?php

namespace App\Tests\Unit\Controllers\Api\V1;

use App\Controllers\Api\V1\PublicContentPageWidgetController;
use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\Widgets\WidgetLayoutOverride;
use App\Enums\PublicContent\WidgetRegion;
use App\Framework\Database\Database;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Widgets\PageWidgetOverrideService;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinition;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;
use App\Services\PublicContent\Widgets\WidgetPlacement;
use App\Services\PublicContent\Widgets\WidgetRegionNormaliser;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentPageWidgetControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $site = Mockery::mock(Site::class)->makePartial();
        $site->id = 7;
        SiteContext::set($site);
    }

    protected function tearDown(): void
    {
        SiteContext::set(null);
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_page_overrides(): void
    {
        $override = new WidgetLayoutOverride('comments', WidgetRegion::Sidebar, 20, true);
        $widgets = $this->widgets();
        $widgets->shouldReceive('getForPage')->once()->with(7, 42)->andReturn([$override]);

        $response = $this->controller(widgets: $widgets)->index(42);
        $payload = json_decode($response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('comments', $payload['widgets'][0]['widget_key']);
        self::assertSame('sidebar', $payload['widgets'][0]['region']);
    }

    public function test_index_returns_404_when_the_page_is_missing(): void
    {
        $response = $this->controller(pages: $this->pages(false))->index(42);
        $payload = json_decode($response->getContent(), true);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Content not found.', $payload['error']);
    }

    public function test_update_rejects_a_non_list_payload(): void
    {
        $response = $this->controller()->update(42, new Request(['widgets' => 'nope']));
        $payload = json_decode($response->getContent(), true);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('Widgets must be a list of overrides.', $payload['error']);
    }

    public function test_update_maps_validation_failures_to_422(): void
    {
        $response = $this->controller()->update(42, new Request([
            'widgets' => [
                ['widget_key' => 'not-a-widget', 'region' => 'sidebar'],
            ],
        ]));
        $payload = json_decode($response->getContent(), true);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('Unknown widget key at index 0.', $payload['error']);
    }

    public function test_update_syncs_overrides(): void
    {
        $saved = [new WidgetLayoutOverride('comments', WidgetRegion::Sidebar, 20, true)];
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('transaction')->once()->andReturnUsing(fn(callable $callback) => $callback());

        $widgets = $this->widgets();
        $widgets->shouldReceive('deleteForPage')->once()->with(7, 42);
        $widgets->shouldReceive('upsert')->once();
        $widgets->shouldReceive('getForPage')->once()->with(7, 42)->andReturn($saved);

        $response = $this->controller(widgets: $widgets, database: $database)->update(42, new Request([
            'widgets' => [
                ['widget_key' => 'comments', 'region' => 'sidebar', 'priority' => 20],
            ],
        ]));
        $payload = json_decode($response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('sidebar', $payload['widgets'][0]['region']);
    }

    private function controller(
        ?PublicContentPageRepository $pages = null,
        ?PageWidgetRepositoryInterface $widgets = null,
        ?Database $database = null,
    ): PublicContentPageWidgetController {
        return new PublicContentPageWidgetController(
            new PageWidgetOverrideService(
                $widgets ?? $this->widgets(),
                $pages ?? $this->pages(true),
                new PublicContentWidgetRegistry([$this->definition()]),
                new WidgetRegionNormaliser(),
                $database ?? Mockery::mock(Database::class),
            ),
        );
    }

    private function widgets(): PageWidgetRepositoryInterface
    {
        $widgets = Mockery::mock(PageWidgetRepositoryInterface::class);
        $widgets->shouldReceive('getForPage')->andReturn([])->byDefault();
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

    private function definition(): PublicContentWidgetDefinition
    {
        return new class implements PublicContentWidgetDefinition {
            public function key(): string
            {
                return 'comments';
            }

            public function defaultPlacement(): WidgetPlacement
            {
                return new WidgetPlacement('comments', 'after-content', 100);
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
