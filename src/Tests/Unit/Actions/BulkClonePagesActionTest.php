<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkClonePages;
use App\Actions\ClonePage;
use App\Models\Page;
use App\Repositories\PageRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkClonePagesActionTest extends FunctionalTestCase
{
    private $clonePage;
    private $pageRepository;
    private $service;

    public function testBulkClonePagesSuccessfully()
    {
        $originalPage = Mockery::mock(Page::class)->makePartial();
        $originalPage->id = 1;
        $originalPage->title = 'Original Page';

        $clonedPage = Mockery::mock(Page::class)->makePartial();
        $clonedPage->id = 2;
        $clonedPage->title = 'Original Page (Copy)';

        $this->clonePage->shouldReceive('handle')->with(1)->andReturn([
            'page' => $clonedPage
        ]);

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($originalPage);
        $this->pageRepository->shouldReceive('update')
            ->with(2, ['title' => 'Original Page', 'status' => 'draft'])
            ->once();

        $results = $this->service->handle([1], ['withPrefix' => false, 'asDraft' => true]);

        $this->assertTrue($results[1]['success']);
        $this->assertEquals(2, $results[1]['cloned_page_id']);
    }

    public function testBulkClonePagesWithPrefix()
    {
        $clonedPage = Mockery::mock(Page::class)->makePartial();
        $clonedPage->id = 2;

        $this->clonePage->shouldReceive('handle')->with(1)->andReturn([
            'page' => $clonedPage
        ]);

        $this->pageRepository->shouldReceive('update')
            ->with(2, ['status' => 'draft'])
            ->once();

        $results = $this->service->handle([1], ['withPrefix' => true, 'asDraft' => true]);

        $this->assertTrue($results[1]['success']);
    }

    public function testBulkClonePagesHandlesFailure()
    {
        $this->clonePage->shouldReceive('handle')->with(999)->andReturn(null);

        $results = $this->service->handle([999]);

        $this->assertFalse($results[999]['success']);
        $this->assertEquals('Page not found', $results[999]['error']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->clonePage = Mockery::mock(ClonePage::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);

        $this->service = new BulkClonePages(
            $this->clonePage,
            $this->pageRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}