<?php

namespace App\Tests\Unit\Services\PublicContent\Composition;

use App\Framework\Support\Collection;
use App\Models\Category;
use App\Models\Page;
use App\Repositories\PublicContent\PublicCategoryRepository;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Composition\PublicLandingSectionProvider;
use App\Services\PublicContent\Images\PublicContentListingImageHydrator;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicLandingSectionProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_batches_category_pages_and_skips_thin_sections(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->page_type = 'landing-page';

        $reviews = Mockery::mock(Category::class)->makePartial();
        $reviews->id = 1;
        $news = Mockery::mock(Category::class)->makePartial();
        $news->id = 2;

        $categories = Mockery::mock(PublicCategoryRepository::class);
        $categories->shouldReceive('getAll')->once()->with(7)->andReturn(new Collection([$reviews, $news]));

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('getPublishedPagesForCategories')
            ->once()
            ->with(7, [1, 2], 6)
            ->andReturn([
                1 => new Collection([
                    Mockery::mock(Page::class)->makePartial(),
                    Mockery::mock(Page::class)->makePartial(),
                    Mockery::mock(Page::class)->makePartial(),
                ]),
                2 => new Collection([
                    Mockery::mock(Page::class)->makePartial(),
                ]),
            ]);

        $images = Mockery::mock(PublicContentListingImageHydrator::class);
        $images->shouldReceive('hydrate')->once()->andReturnUsing(static fn(Collection $pages): Collection => $pages);

        $sections = (new PublicLandingSectionProvider($categories, $pages, $images))->for($page, 7);

        self::assertCount(1, $sections);
        self::assertSame(1, $sections[0]['category']->id);
        self::assertCount(3, $sections[0]['pages']);
    }

    public function test_returns_empty_for_non_landing_pages(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->page_type = 'article';

        $categories = Mockery::mock(PublicCategoryRepository::class);
        $categories->shouldReceive('getAll')->never();

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('getPublishedPagesForCategories')->never();
        $images = Mockery::mock(PublicContentListingImageHydrator::class);
        $images->shouldReceive('hydrate')->never();

        self::assertSame(
            [],
            (new PublicLandingSectionProvider($categories, $pages, $images))->for($page, 7),
        );
    }
}
