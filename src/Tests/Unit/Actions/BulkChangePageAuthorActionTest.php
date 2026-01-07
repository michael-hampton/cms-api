<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkChangePageAuthor;
use App\Models\Page;
use App\Repositories\PageRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkChangePageAuthorActionTest extends FunctionalTestCase
{
    private $pageRepository;
    private $service;

    public function testBulkChangeAuthorSuccessfully()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();
        $page2 = Mockery::mock(Page::class)->makePartial();

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page1);
        $this->pageRepository->shouldReceive('find')->with(2)->andReturn($page2);

        $this->pageRepository->shouldReceive('update')
            ->with(1, ['author_id' => 5])
            ->once();
        $this->pageRepository->shouldReceive('update')
            ->with(2, ['author_id' => 5])
            ->once();

        $results = $this->service->handle([1, 2], 5);

        $this->assertTrue($results[1]['success']);
        $this->assertTrue($results[2]['success']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->service = new BulkChangePageAuthor($this->pageRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}