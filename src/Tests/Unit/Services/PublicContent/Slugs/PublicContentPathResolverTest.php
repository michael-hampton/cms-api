<?php

namespace App\Tests\Unit\Services\PublicContent\Slugs;

use App\Models\Page;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\SiteRepository;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentPathResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testResolvesFlatSlug(): void
    {
        $resolver = new PublicContentPathResolver(
            $this->pageRepository(),
            $this->siteRepository(),
        );

        $candidates = $resolver->resolveCandidates(999999, 'about-us');

        self::assertSame('about-us', $candidates[0]->slug);
        self::assertNull($candidates[0]->categorySlug);
    }

    public function testResolvesNestedCategoryPath(): void
    {
        $resolver = new PublicContentPathResolver(
            $this->pageRepository(),
            $this->siteRepository(),
        );

        $candidates = $resolver->resolveCandidates(999999, 'news/local/my-article');
        $candidate = null;

        foreach ($candidates as $item) {
            if ($item->matchedPattern === '{category}/{subcategory}/{slug}') {
                $candidate = $item;
                break;
            }
        }

        self::assertNotNull($candidate);
        self::assertSame('my-article', $candidate->slug);
        self::assertSame('news', $candidate->categorySlug);
        self::assertSame('local', $candidate->subcategorySlug);
    }

    public function testPageCustomRouteOverridesSiteSlugPatternForCanonicalPath(): void
    {
        $page = new Page();
        $page->custom_route = 'special/features/custom-url';

        $resolver = new PublicContentPathResolver(
            $this->pageRepository(),
            $this->siteRepository(),
        );

        self::assertSame(
            'special/features/custom-url',
            $resolver->canonicalPathForPage($page)
        );
    }

    public function testCustomRouteIsResolvedBeforeConfiguredSlugCandidates(): void
    {
        $page = new Page();
        $page->id = 123;
        $page->slug = 'grid-safe-custom-route-page';

        $pageRepository = $this->pageRepository($page, 'features/grid-safe-link');

        $resolver = new PublicContentPathResolver(
            $pageRepository,
            $this->siteRepository(),
        );

        $candidates = $resolver->resolveCandidates(999999, 'features/grid-safe-link');

        self::assertNotEmpty($candidates);
        self::assertSame('grid-safe-custom-route-page', $candidates[0]->slug);
        self::assertSame('{custom_route}', $candidates[0]->matchedPattern);
        self::assertSame('features/grid-safe-link', $candidates[0]->path);
    }

    private function pageRepository(?Page $page = null, ?string $customRoute = null): PageRepository
    {
        $pageRepository = Mockery::mock(PageRepository::class);

        $pageRepository
            ->shouldReceive('findPublishedByCustomRoute')
            ->byDefault()
            ->andReturn(null);

        if ($page instanceof Page && $customRoute !== null) {
            $pageRepository
                ->shouldReceive('findPublishedByCustomRoute')
                ->with(999999, $customRoute)
                ->andReturn($page);
        }

        return $pageRepository;
    }

    private function siteRepository(): SiteRepository
    {
        $siteRepository = Mockery::mock(SiteRepository::class);

        $siteRepository
            ->shouldReceive('find')
            ->byDefault()
            ->andReturn(null);

        return $siteRepository;
    }
}
