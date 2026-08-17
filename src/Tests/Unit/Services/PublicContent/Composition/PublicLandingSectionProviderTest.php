<?php

namespace App\Tests\Unit\Services\PublicContent\Composition;

use App\Framework\Support\Collection;
use App\Models\Category;
use App\Models\Page;
use App\Repositories\PublicContent\PublicCategoryRepository;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Composition\PublicLandingSectionProvider;
use App\Services\PublicContent\Config\PublicContentConfigSource;
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

        $sections = $this->provider($categories, $pages, $images)->for($page, 7);

        self::assertCount(1, $sections);
        self::assertSame(1, $sections[0]['category']->id);
        self::assertCount(3, $sections[0]['pages']);
    }

    public function test_uses_configured_section_limits(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->page_type = 'landing-page';

        $reviews = Mockery::mock(Category::class)->makePartial();
        $reviews->id = 1;

        $categories = Mockery::mock(PublicCategoryRepository::class);
        $categories->shouldReceive('getAll')->once()->with(7)->andReturn(new Collection([$reviews]));

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('getPublishedPagesForCategories')
            ->once()
            ->with(7, [1], 4)
            ->andReturn([
                1 => new Collection([
                    Mockery::mock(Page::class)->makePartial(),
                    Mockery::mock(Page::class)->makePartial(),
                ]),
            ]);

        $images = Mockery::mock(PublicContentListingImageHydrator::class);
        $images->shouldReceive('hydrate')->once()->andReturnUsing(static fn(Collection $pages): Collection => $pages);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->with(7, 'widgets.category-pages.page_types', ['landing-page'])
            ->andReturn(['landing-page']);
        $config->shouldReceive('get')->with(7, 'widgets.category-pages.pages_per_section', 6)->andReturn(4);
        $config->shouldReceive('get')->with(7, 'widgets.category-pages.min_pages', 3)->andReturn(2);

        $sections = $this->provider($categories, $pages, $images, $config)->for($page, 7);

        self::assertCount(1, $sections);
        self::assertCount(2, $sections[0]['pages']);
    }

    public function test_clamps_min_pages_to_pages_per_section(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->page_type = 'landing-page';

        $reviews = Mockery::mock(Category::class)->makePartial();
        $reviews->id = 1;

        $categories = Mockery::mock(PublicCategoryRepository::class);
        $categories->shouldReceive('getAll')->once()->with(7)->andReturn(new Collection([$reviews]));

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('getPublishedPagesForCategories')
            ->once()
            ->with(7, [1], 1)
            ->andReturn([
                1 => new Collection([
                    Mockery::mock(Page::class)->makePartial(),
                ]),
            ]);

        $images = Mockery::mock(PublicContentListingImageHydrator::class);
        $images->shouldReceive('hydrate')->once()->andReturnUsing(static fn(Collection $pages): Collection => $pages);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->with(7, 'widgets.category-pages.page_types', ['landing-page'])
            ->andReturn(['landing-page']);
        $config->shouldReceive('get')->with(7, 'widgets.category-pages.pages_per_section', 6)->andReturn(1);
        $config->shouldReceive('get')->with(7, 'widgets.category-pages.min_pages', 3)->andReturn(3);

        $sections = $this->provider($categories, $pages, $images, $config)->for($page, 7);

        self::assertCount(1, $sections);
        self::assertCount(1, $sections[0]['pages']);
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
            $this->provider($categories, $pages, $images)->for($page, 7),
        );
    }

    private function provider(
        PublicCategoryRepository $categories,
        PublicContentPageRepository $pages,
        PublicContentListingImageHydrator $images,
        ?PublicContentConfigSource $config = null,
    ): PublicLandingSectionProvider {
        if ($config === null) {
            $config = Mockery::mock(PublicContentConfigSource::class);
            $config->shouldReceive('get')->with(7, 'widgets.category-pages.page_types', ['landing-page'])
                ->andReturn(['landing-page']);
            $config->shouldReceive('get')->with(7, 'widgets.category-pages.pages_per_section', 6)->andReturn(6);
            $config->shouldReceive('get')->with(7, 'widgets.category-pages.min_pages', 3)->andReturn(3);
        }

        return new PublicLandingSectionProvider($categories, $pages, $images, $config);
    }
}
