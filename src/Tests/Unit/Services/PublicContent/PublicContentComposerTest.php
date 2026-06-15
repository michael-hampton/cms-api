<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\DTO\PublicContent\PublicContentContext;
use App\Framework\Support\Collection;
use App\Framework\View\ViewRenderer;
use App\Models\Page;
use App\Services\PublicContent\Composition\PublicContentComposer;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentComposerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testItBuildsOrderedComponentRegionsWithoutAViewIncludeList(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->page_type = 'article';
        $page->products = new Collection();

        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')
            ->andReturnUsing(static fn(string $template): string => '<div data-template="' . $template . '"></div>');

        $composer = new PublicContentComposer($views);
        $regions = $composer->compose(new PublicContentContext(
            page: $page,
            siteId: 1,
            siteSlug: 'estate',
            member: null,
            viewData: [
                'categories' => new Collection(),
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

        self::assertSame(
            ['page-title', 'category-pills', 'tags', 'page-actions'],
            array_map(static fn($component) => $component->type, $regions['header']),
        );
        self::assertSame(
            ['activity-feed-widget', 'trending-widget', 'newsletter-signup-widget', 'comments', 'social-links'],
            array_map(static fn($component) => $component->type, $regions['after-content']),
        );
        self::assertSame(
            ['guest-contributors', 'authors'],
            array_map(static fn($component) => $component->type, $regions['below-content']),
        );
    }
}
