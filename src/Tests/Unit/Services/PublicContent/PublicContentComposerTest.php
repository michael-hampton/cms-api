<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\DTO\PublicContent\PublicContentContext;
use App\Framework\Support\Collection;
use App\Framework\View\ViewRenderer;
use App\Models\Page;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Services\PublicContent\Composition\PublicContentComposer;
use App\Services\PublicContent\Composition\PublicContentWidgetDiagnostics;
use App\Services\PublicContent\Composition\RegionalPublicContentComponentFactory;
use App\Services\PublicContent\Paywall\PublicContentPaywallModeResolver;
use App\Services\PublicContent\Widgets\BuiltInPublicContentWidgetCatalog;
use App\Services\PublicContent\Widgets\PageWidgetLayoutResolver;
use App\Services\PublicContent\Widgets\PaywallOverlayWidget;
use App\Services\PublicContent\Widgets\PublicContentWidgetEligibility;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentComposerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testArticleWidgets(): void
    {
        $regions = $this->compose('article', true);

        self::assertSame(
            ['page-title', 'category-pills', 'tags', 'page-actions'],
            $this->types($regions['header']),
        );
        self::assertSame(['authors'], $this->types($regions['below-content']));
    }

    public function testLandingWidgets(): void
    {
        $regions = $this->compose('landing-page');

        self::assertSame(['page-title'], $this->types($regions['header']));
        self::assertContains('newsletter-signup-widget', $this->types($regions['after-content']));
        self::assertContains('guest-contributors', $this->types($regions['below-content']));
    }

    public function testStaticPageOmitsEditorialWidgetsAndActions(): void
    {
        $regions = $this->compose('page', true);

        self::assertSame(['page-title'], $this->types($regions['header']));
        self::assertSame(['authors'], $this->types($regions['below-content']));
        self::assertNotContains('comments', $this->types($regions['after-content']));
    }

    private function compose(string $pageType, bool $withAuthor = false): array
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 42;
        $page->page_type = $pageType;
        $page->products = new Collection();
        $page->authors = $withAuthor
            ? new Collection([(object) ['name' => 'Author', 'slug' => 'author']])
            : new Collection();
        $page->title = 'Example';
        $page->slug = 'example';
        $page->is_paid = false;
        $page->is_public_contribution = false;

        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')->andReturn('<div>component</div>');

        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->with(42)->andReturn(new Collection());

        $paywallMode = new PublicContentPaywallModeResolver();
        $registry = new PublicContentWidgetRegistry([
            new PaywallOverlayWidget($views, $paywallMode),
        ]);

        $composer = new PublicContentComposer(
            new BuiltInPublicContentWidgetCatalog($views, new PublicContentWidgetEligibility()),
            new RegionalPublicContentComponentFactory($views),
            $registry,
            new PageWidgetLayoutResolver($repository),
            new PublicContentWidgetDiagnostics(),
        );

        return $composer->compose(new PublicContentContext(
            page: $page,
            siteId: 1,
            siteSlug: 'estate',
            member: null,
            viewData: [
                'access' => ['can_view' => true, 'reason' => null],
                'categories' => new Collection(),
                'categoriesWithPages' => [],
                'feedPages' => new Collection(),
                'trendingPages' => new Collection(),
                'todaysDeals' => [],
                'links' => [
                    'viewer_state' => '/viewer',
                    'comments' => '/comments',
                    'like' => '/like',
                    'view' => '/views',
                ],
            ],
        ));
    }

    private function types(array $components): array
    {
        return array_map(static fn($component) => $component->type, $components);
    }
}
