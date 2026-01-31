<?php

namespace App\Tests\Unit\Actions\Page;

use App\Actions\Pages\BulkApprovePages;
use App\Models\Page;
use App\Services\Cms\Pages\PageService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkApprovePagesActionTest extends FunctionalTestCase
{
    use CreatesTestData, HasSiteHistory;

    private $pageService;

    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        ini_set('log_errors', 0);

       $this->pageService = Mockery::mock(PageService::class);

        $this->service = new BulkApprovePages(
            $this->pageService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testBulkApprovePages()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();
        $page1->id = 1;
        $page1->status = 'waiting_approval';
        $page1->shouldReceive('isWaitingApproval')->andReturn(true);

        $page2 = Mockery::mock(Page::class)->makePartial();
        $page2->id = 2;
        $page2->status = 'waiting_approval';
        $page2->shouldReceive('isWaitingApproval')->andReturn(true);

        $this->pageService->shouldReceive('approvePage')->with(1, 1)->andReturn($page1);
        $this->pageService->shouldReceive('approvePage')->with(2, 1)->andReturn($page2);

        $results = $this->service->handle([1, 2], 1);

        $this->assertCount(2, $results);
        $this->assertTrue($results[1]['success']);
        $this->assertTrue($results[2]['success']);
    }
}