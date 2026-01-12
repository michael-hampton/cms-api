<?php

namespace App\Tests\Unit\Actions\Page;

use App\Actions\Pages\BulkDeletePages;
use App\Services\Cms\PageService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkDeletePagesActionTest extends FunctionalTestCase
{
    use CreatesTestData, HasSiteHistory;

    private $pageService;
    private $service;
    protected function setUp(): void
    {
        parent::setUp();

        ini_set('log_errors', 0);

        $this->pageService = Mockery::mock(PageService::class);


        $this->service = new BulkDeletePages(
            $this->pageService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testBulkDeletePagesDeletesMultiplePages()
    {
        $page1 = $this->createPage();
        $page2 = $this->createPage();
        $page3 = $this->createPage();

        $this->pageService->shouldReceive('deletePage')->times(3)->andReturn(true);

        $results = $this->service->handle([$page1->id, $page2->id, $page3->id]);

        $this->assertCount(3, $results);
        $this->assertTrue($results[$page1->id]['success']);
        $this->assertTrue($results[$page2->id]['success']);
        $this->assertTrue($results[$page3->id]['success']);
    }
}