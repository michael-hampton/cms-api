<?php

namespace App\Tests\Unit\Services\PublicContent\Preview;

use App\DTO\PublicContent\ResolvedPublicContentPath;
use App\Models\Category;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Preview\PublicContentPreviewPageResolver;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

final class PublicContentPreviewPageResolverTest extends MockeryTestCase
{
    public function test_it_returns_first_candidate_that_matches_page_type_and_category(): void
    {
        $paths = Mockery::mock(PublicContentPathResolver::class);
        $pages = Mockery::mock(PublicContentPageRepository::class);

        $flatCandidate = new ResolvedPublicContentPath(
            path: 'category/widgets/foo',
            slug: 'category/widgets/foo',
            categorySlug: null,
            subcategorySlug: null,
            pageType: null,
            matchedPattern: '{path}',
        );
        $categoryCandidate = new ResolvedPublicContentPath(
            path: 'category/widgets/foo',
            slug: 'foo',
            categorySlug: 'widgets',
            subcategorySlug: null,
            pageType: null,
            matchedPattern: '{category}/{slug}',
        );

        $paths->shouldReceive('resolveCandidates')
            ->once()
            ->with(5, 'category/widgets/foo')
            ->andReturn([$flatCandidate, $categoryCandidate]);

        $pages->shouldReceive('findCompletePublishedBySlug')
            ->once()
            ->with(5, 'category/widgets/foo')
            ->andReturn(null);

        $category = Mockery::mock(Category::class)->makePartial();
        $category->slug = 'widgets';

        // FIX: Use a simple anonymous class stub so method_exists() evaluates to true
        $categories = new class($category) {
            private array $items;
            public function __construct($item) { $this->items = [$item]; }
            public function all(): array { return $this->items; }
        };

        $page = Mockery::mock(Page::class)->makePartial();
        $page->page_type = 'article';
        $page->categories = $categories;

        $pages->shouldReceive('findCompletePublishedBySlug')
            ->once()
            ->with(5, 'foo')
            ->andReturn($page);

        $resolver = new PublicContentPreviewPageResolver($paths, $pages);

        self::assertSame($page, $resolver->resolve(5, 'category/widgets/foo'));
    }

    public function test_it_returns_null_when_no_candidate_matches(): void
    {
        $paths = Mockery::mock(PublicContentPathResolver::class);
        $pages = Mockery::mock(PublicContentPageRepository::class);

        $paths->shouldReceive('resolveCandidates')->once()->andReturn([]);

        $resolver = new PublicContentPreviewPageResolver($paths, $pages);

        self::assertNull($resolver->resolve(5, 'nowhere'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}