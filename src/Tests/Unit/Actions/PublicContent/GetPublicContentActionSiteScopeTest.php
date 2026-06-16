<?php

namespace App\Tests\Unit\Actions\PublicContent;

use App\Actions\PublicContent\GetPublicContentAction;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\PublicContent\Composition\PublicContentComposer;
use App\Services\PublicContent\Composition\PublicContentCompositionData;
use App\Services\PublicContent\PublicContentRenderer;
use Mockery;
use PHPUnit\Framework\TestCase;
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

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findCompletePublishedBySlug')
            ->once()
            ->with(7, 'home')
            ->andReturn($page);

        $access = Mockery::mock(ArticleAccessService::class);
        $access->shouldNotReceive('canView');

        $renderer = Mockery::mock(PublicContentRenderer::class);
        $renderer->shouldNotReceive('render');

        $compositionData = Mockery::mock(PublicContentCompositionData::class);
        $compositionData->shouldNotReceive('build');

        $composer = Mockery::mock(PublicContentComposer::class);
        $composer->shouldNotReceive('compose');

        $action = new GetPublicContentAction(
            $pages,
            $access,
            $renderer,
            $compositionData,
            $composer,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Public content site scope mismatch.');

        $action->execute(7, 'home');
    }
}
