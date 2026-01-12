<?php

namespace App\Tests\Unit\Actions\Page;

use App\Actions\Pages\BulkChangePageAuthors;
use App\Models\Page;
use App\Repositories\Cms\PageAuthorRepository;
use App\Repositories\Cms\PageRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkChangePageAuthorsActionTest extends FunctionalTestCase
{
    private $pageRepository;
    private $pageAuthorRepository;
    private $service;

    public function testBulkChangeAuthorsSuccessfully()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();
        $page2 = Mockery::mock(Page::class)->makePartial();

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page1);
        $this->pageRepository->shouldReceive('find')->with(2)->andReturn($page2);

        $this->pageAuthorRepository->shouldReceive('syncAuthors')
            ->with(1, [5], 'author', 1)
            ->once();
        $this->pageAuthorRepository->shouldReceive('syncAuthors')
            ->with(2, [5], 'author', 1)
            ->once();

        $results = $this->service->handle([1, 2], 5, 1, 'author');

        $this->assertTrue($results[1]['success']);
        $this->assertTrue($results[2]['success']);
    }

    public function testBulkChangeAuthorsHandlesPageNotFound()
    {
        $this->pageRepository->shouldReceive('find')->with(999)->andReturn(null);

        $results = $this->service->handle([999], 5, 1, 'author');

        $this->assertFalse($results[999]['success']);
        $this->assertEquals('Page not found', $results[999]['error']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->pageAuthorRepository = Mockery::mock(PageAuthorRepository::class);

        $this->service = new BulkChangePageAuthors(
            $this->pageRepository,
            $this->pageAuthorRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}