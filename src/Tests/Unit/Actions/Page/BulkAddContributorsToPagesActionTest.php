<?php

namespace App\Tests\Unit\Actions\Page;

use App\Actions\Pages\BulkAddContributorsToPages;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Repositories\Cms\Pages\PageAuthorRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkAddContributorsToPagesActionTest extends FunctionalTestCase
{
    private $pageRepository;
    private $pageAuthorRepository;
    private $service;

    public function testBulkAddContributorsSuccessfully()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();
        $page2 = Mockery::mock(Page::class)->makePartial();

        $pageAuthor = Mockery::mock(PageAuthor::class)->makePartial();

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page1);
        $this->pageRepository->shouldReceive('find')->with(2)->andReturn($page2);

        $this->pageAuthorRepository->shouldReceive('getAuthorsForPage')->with(1, 'contributor')->andReturn([10]);
        $this->pageAuthorRepository->shouldReceive('getAuthorsForPage')->with(2, 'contributor')->andReturn([20]);

        $this->pageAuthorRepository->shouldReceive('syncAuthors')
            ->with(1, Mockery::on(function ($authorIds) {
                sort($authorIds);
                return $authorIds === [10, 30, 40];
            }), 'contributor', 1)
            ->once();

        $this->pageAuthorRepository->shouldReceive('syncAuthors')
            ->with(2, Mockery::on(function ($authorIds) {
                sort($authorIds);
                return $authorIds === [20, 30, 40];
            }), 'contributor', 1)
            ->once();

        $results = $this->service->handle([1, 2], [30, 40], 1, 'contributor');

        $this->assertTrue($results[1]['success']);
        $this->assertTrue($results[2]['success']);
    }

    public function testBulkAddContributorsHandlesPageNotFound()
    {
        $this->pageRepository->shouldReceive('find')->with(999)->andReturn(null);

        $results = $this->service->handle([999], [30], 1, 'contributor');

        $this->assertFalse($results[999]['success']);
        $this->assertEquals('Page not found', $results[999]['error']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->pageAuthorRepository = Mockery::mock(PageAuthorRepository::class);

        $this->service = new BulkAddContributorsToPages(
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