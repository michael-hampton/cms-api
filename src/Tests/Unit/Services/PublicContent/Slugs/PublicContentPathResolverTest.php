<?php

namespace App\Tests\Unit\Services\PublicContent\Slugs;

use App\Models\Page;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;

final class PublicContentPathResolverTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runMigrations();
    }

    public function testPageCustomRouteOverridesSiteSlugPatternForCanonicalPath(): void
    {
        $page = Page::create([
            'title' => 'Custom Route Page',
            'slug' => 'custom-route-page',
            'status' => 'published',
            'page_type' => 'article',
            'site_id' => $this->siteId,
            'custom_route' => 'special/features/custom-url',
        ]);

        $page = Page::with(['categories'])->find((int) $page->id);

        $resolver = new PublicContentPathResolver();

        $this->assertSame('special/features/custom-url', $resolver->canonicalPathForPage($page));
    }

    public function testCustomRouteIsResolvedBeforeConfiguredSlugCandidates(): void
    {
        Page::create([
            'title' => 'Grid Safe Custom Route Page',
            'slug' => 'grid-safe-custom-route-page',
            'status' => 'published',
            'page_type' => 'article',
            'site_id' => $this->siteId,
            'custom_route' => 'features/grid-safe-link',
        ]);

        $resolver = new PublicContentPathResolver();
        $candidates = $resolver->resolveCandidates($this->siteId, 'features/grid-safe-link');

        $this->assertNotEmpty($candidates);
        $this->assertSame('grid-safe-custom-route-page', $candidates[0]->slug);
        $this->assertSame('{custom_route}', $candidates[0]->matchedPattern);
    }
}
