<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\SiteRepository;
use App\Services\PublicContent\Config\PublicContentConfigSource;
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
            $this->configSource(),
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
            $this->configSource(),
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

    private function pageRepository(): PageRepository
    {
        $pageRepository = Mockery::mock(PageRepository::class);

        $pageRepository
            ->shouldReceive('findPublishedByCustomRoute')
            ->byDefault()
            ->andReturn(null);

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

    private function configSource(): PublicContentConfigSource
    {
        $configSource = Mockery::mock(PublicContentConfigSource::class);

        // Explicitly define the method call expected during layout pattern generation
        $configSource
            ->shouldReceive('get')
            ->with(999999, 'slug_patterns', Mockery::type('array'))
            ->andReturn([])
            ->byDefault();

        return $configSource;
    }
}