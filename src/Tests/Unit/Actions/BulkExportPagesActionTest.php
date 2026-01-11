<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkExportPages;
use App\Models\Page;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\PageRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkExportPagesActionTest extends FunctionalTestCase
{
    private $pageRepository;
    private $blockRepository;
    private $service;

    public function testExportPagesAsJson()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->title = 'Test Page';
        $page->slug = 'test-page';
        $page->status = 'published';

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn($page);

        $this->blockRepository->shouldReceive('getBlocksForPage')
            ->with(1)
            ->andReturn(collect([]));

        $result = $this->service->handle([1], 'json', true);

        $this->assertJson($result);
        $data = json_decode($result, true);
        $this->assertCount(1, $data);
        $this->assertEquals('Test Page', $data[0]['title']);
    }

    public function testExportPagesAsCsv()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->title = 'Test Page';
        $page->slug = 'test-page';
        $page->status = 'published';

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn($page);

        $this->blockRepository->shouldReceive('getBlocksForPage')
            ->never(); // CSV doesn't include blocks

        $result = $this->service->handle([1], 'csv', false);

        $this->assertStringContainsString('id,title,slug,status', $result);
        $this->assertStringContainsString('Test Page', $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->blockRepository = Mockery::mock(BlockRepository::class);
        $this->service = new BulkExportPages(
            $this->pageRepository,
            $this->blockRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}