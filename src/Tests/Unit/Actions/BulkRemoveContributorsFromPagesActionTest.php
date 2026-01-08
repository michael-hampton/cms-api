<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkRemoveContributorsFromPages;
use App\Models\Page;
use App\Repositories\PageAuthorRepository;
use App\Repositories\PageRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkRemoveContributorsFromPagesActionTest extends FunctionalTestCase
{
    private $pageRepository;
    private $pageAuthorRepository;
    private $service;

    public function testBulkRemoveContributorsSuccessfully()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page1);

        $this->pageAuthorRepository->shouldReceive('getAuthorsForPage')
            ->with(1, 'contributor')
            ->andReturn([10, 20, 30]);

        $this->pageAuthorRepository->shouldReceive('syncAuthors')
            ->once()
            ->with(1, Mockery::on(function ($authorIds) {
                sort($authorIds);
                return $authorIds === [10, 30]; // 20 removed
            }), 'contributor', 1);

        $results = $this->service->handle([1], [20], 1, 'contributor');

        $this->assertTrue($results[1]['success']);
    }

    public function testBulkRemoveContributorsHandlesPageNotFound()
    {
        $this->pageRepository->shouldReceive('find')->with(999)->andReturn(null);

        $results = $this->service->handle([999], [20], 1, 'contributor');

        $this->assertFalse($results[999]['success']);
        $this->assertEquals('Page not found', $results[999]['error']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->pageAuthorRepository = Mockery::mock(PageAuthorRepository::class);

        $this->service = new BulkRemoveContributorsFromPages(
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