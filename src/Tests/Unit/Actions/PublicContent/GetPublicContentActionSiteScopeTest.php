<?php

namespace App\Tests\Unit\Actions\PublicContent;

use App\Actions\PublicContent\GetPublicContentAction;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageGridRepository;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Repositories\PublicContent\PublicTerritoryRepository;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\PublicContent\Composition\PublicContentComposer;
use App\Services\PublicContent\Composition\PublicContentCompositionData;
use App\Services\PublicContent\Images\PublicContentImageUrlTransformer;
use App\Services\PublicContent\Inheritance\PublicContentEffectivePageResolver;
use App\Services\PublicContent\Layout\PublicContentLayoutPrecedenceResolver;
use App\Services\PublicContent\Paywall\PublicContentPaywallModeResolver;
use App\Services\PublicContent\PublicContentRenderer;
use App\Services\PublicContent\Slugs\PublicContentLinkRewriter;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class GetPublicContentActionSiteScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testItRejectsAPageFromAnotherSiteBeforeRendering(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 10;
        $page->site_id = 1;
        $page->slug = 'home';
        $page->status = 'published';
        $page->territory_id = null;

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findCompletePublishedBySlug')
            ->once()
            ->with(7, 'home')
            ->andReturn($page);

        $territories = Mockery::mock(PublicTerritoryRepository::class);
        $territories->shouldNotReceive('findActiveBySlug');
        $territories->shouldNotReceive('findActiveById');

        $action = new GetPublicContentAction(
            $pages,
            $territories,
            $this->unused(ArticleAccessService::class),
            $this->unused(PublicContentRenderer::class),
            $this->unused(PublicContentImageUrlTransformer::class),
            $this->unused(PublicContentCompositionData::class),
            $this->unused(PublicContentComposer::class),
            $this->unused(PageGridRepository::class),
            $this->unused(PublicContentPaywallModeResolver::class),
            $this->unused(PublicContentPathResolver::class),
            $this->unused(PublicContentLinkRewriter::class),
            $this->unused(PublicContentEffectivePageResolver::class),
            $this->unused(PublicContentLayoutPrecedenceResolver::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Public content site scope mismatch.');

        $action->execute(7, 'home');
    }

    private function unused(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
