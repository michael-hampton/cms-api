<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\DTO\PublicContent\PublicContentContext;
use App\Framework\Support\Collection;
use App\Framework\View\ViewRenderer;
use App\Models\Page;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Services\PublicContent\Composition\PublicContentComposer;
use App\Services\PublicContent\Composition\RegionalPublicContentComponentFactory;
use App\Services\PublicContent\Widgets\PageWidgetLayoutResolver;
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

    public function testArticleDoesNotContainHomepageOnlyComponents(): void
    {
        $regions = $this->compose('article');

        self::assertSame(
            ['page-title', 'category-pills', 'tags', 'page-actions'],
            array_map(static fn($component) => $component->type, $regions['header']),
        );
        self::assertSame(
            ['trending-widget', 'newsletter-signup-widget', 'comments', 'social-links'],
            array_map(static fn($component) => $component->type, $regions['after-content']),
        );
        self::assertSame(
            ['authors'],
            array_map(static fn($component) => $component->type, $regions['below-content']),
        );
    }

    public function testLandingPageContainsHomepageOnlyComponents(): void
    {
        $regions = $this->compose('landing-page', withCategories: true);

        self::assertSame(
            ['page-title'],
            array_map(static fn($component) => $component->type, $regions['header']),
        );
        self::assertContains(
            'activity-feed-widget',
            array_map(static fn($component) => $component->type, $regions['after-content']),
        );
        self::assertContains(
            'guest-contributors',
            array_map(static fn($component) => $component->type, $regions['below-content']),
        );
    }

    private function compose(string $pageType, bool $withCategories = false): array
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 42;
        $page->page_type = $pageType;
        $page->products = new Collection();

        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')
            ->andReturnUsing(static fn(string $template): string => '<div data-template="' . $template . '"></div>');

        $pageWidgets = Mockery::mock(PageWidgetRepositoryInterface::class);
        $pageWidgets->shouldReceive('getForPage')
            ->once()
            ->with(42)
            ->andReturn(new Collection());

        return (new PublicContentComposer(
            $views,
            new RegionalPublicContentComponentFactory($views),
            new PublicContentWidgetRegistry(),
            new PageWidgetLayoutResolver($pageWidgets),
        ))->compose(new PublicContentContext(
            page: $page,
            siteId: 1,
            siteSlug: 'estate',
            member: null,
            viewData: [
                'categories' => $withCategories ? new Collection([(object)['id' => 1]]) : new Collection(),
                'categoriesWithPages' => [],
                'feedPages' => new Collection(),
                'trendingPages' => new Collection(),
                'todaysDeals' => [],
                'comments' => [],
                'likeCount' => 0,
                'viewCount' => 0,
                'isLiked' => false,
                'links' => [
                    'viewer_state' => '/viewer',
                    'comments' => '/comments',
                    'like' => '/like',
                    'view' => '/views',
                ],
            ],
        ));
    }
}
