<?php

namespace App\Tests\Unit\Actions\Page;

use App\Actions\Pages\BulkUpdatePageStatus;
use App\Models\Page;
use App\Repositories\Cms\PageRepository;
use App\Services\Cms\PageService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkUpdatePageStatusActionTest extends FunctionalTestCase
{
    use CreatesTestData, HasSiteHistory;

    private $pageRepository;

    private $service;
    private $pageService;

    protected function setUp(): void
    {
        parent::setUp();

        ini_set('log_errors', 0);

        $this->pageService = Mockery::mock(PageService::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);


        $this->service = new BulkUpdatePageStatus(
            $this->pageRepository,
            $this->pageService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testBulkUpdateStatusUpdatesMultiplePages()
    {
        $page1 = $this->createPage(['status' => 'draft']);
        $page2 = $this->createPage(['status' => 'draft']);

        $this->pageRepository->shouldReceive('find')
            ->times(2)
            ->andReturn($page1, $page2);

        $this->pageService->shouldReceive('updatePageWithAllData')
            ->once()
            ->with($page1->id, Mockery::any(), $this->siteId)
            ->andReturn($page1);

        $this->pageService->shouldReceive('updatePageWithAllData')
            ->once()
            ->with($page2->id, Mockery::any(), $this->siteId)
            ->andReturn($page2);

        $results = $this->service->handle([$page1->id, $page2->id], 'published', $this->siteId);

        $this->assertCount(2, $results);
        $this->assertTrue($results[$page1->id]['success']);
        $this->assertTrue($results[$page2->id]['success']);
    }

    public function testBulkUpdateStatusThrowsExceptionForInvalidStatus()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid status value');

        $this->service->handle([1, 2], 'invalid-status');
    }

    public function testBulkUpdateStatusValidatesTransitions()
    {
        $archivedPage = Mockery::mock(Page::class)->makePartial();
        $archivedPage->status = 'archived';
        $archivedPage->id = 1;

        $draftPage = Mockery::mock(Page::class)->makePartial();
        $draftPage->status = 'draft';
        $draftPage->id = 2;

        $this->pageRepository->shouldReceive('find')
            ->with($archivedPage->id)
            ->andReturn($archivedPage);

        $this->pageRepository->shouldReceive('find')
            ->with($draftPage->id)
            ->andReturn($draftPage);

        $this->pageService->shouldReceive('updatePageWithAllData')
            ->once()
            ->with($draftPage->id, Mockery::any(), $this->siteId)
            ->andReturn($draftPage);

        $results = $this->service->handle(
            [$archivedPage->id, $draftPage->id],
            'published',
            $this->siteId
        );

        // Archived page should fail
        $this->assertFalse($results[$archivedPage->id]['success']);
        $this->assertStringContainsString('Cannot change status', $results[$archivedPage->id]['error']);

        // Draft page should succeed
        $this->assertTrue($results[$draftPage->id]['success']);
    }

    public function testBulkUpdateStatusHandlesApprovalWorkflow()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->status = 'draft';
        $page->requires_approval = true;
        $page->id = 1;

        $this->pageRepository->shouldReceive('find')
            ->with($page->id)
            ->andReturn($page);

        $this->pageService->shouldReceive('updatePageWithAllData')
            ->once()
            ->with($page->id, Mockery::any(), $this->siteId)
            ->andReturn($page);

        $results = $this->service->handle([$page->id], 'published', $this->siteId);

        $this->assertTrue($results[$page->id]['success']);
    }

    public function testBulkUpdateStatusHandlesPageNotFound()
    {
        $this->pageRepository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $results = $this->service->handle([999], 'published');

        $this->assertFalse($results[999]['success']);
        $this->assertEquals('Page not found', $results[999]['error']);
    }
}