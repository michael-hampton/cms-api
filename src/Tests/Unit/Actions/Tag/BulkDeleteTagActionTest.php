<?php

namespace App\Tests\Unit\Actions\Tag;

use App\Actions\Tag\BulkDeleteTag;
use App\Framework\Database\Database;
use App\Models\Tag;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkDeleteTagActionTest extends FunctionalTestCase
{
    use HasSiteHistory;

    protected $repository;
    protected $service;
    private $databaseMock;
    private $pageRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(TagRepository::class);
        $this->service = new BulkDeleteTag($this->databaseMock, $this->repository, $this->pageRepository);
    }

    public function testBulkDeleteSuccessfully(): void
    {
        $tag1 = Mockery::mock(Tag::class)->makePartial();
        $tag2 = Mockery::mock(Tag::class)->makePartial();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($tag1);

        $this->repository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($tag2);

        $this->repository->shouldReceive('getPagesByTagId')
            ->twice()
            ->andReturn(collect([]));

        $tag1->shouldReceive('delete')->once()->andReturn(true);
        $tag2->shouldReceive('delete')->once()->andReturn(true);

        $result = $this->service->handle([1, 2]);

        $this->assertCount(2, $result['deleted']);
        $this->assertCount(0, $result['failed']);
    }

    public function testBulkDeleteFailsWhenPagesExist(): void
    {
        $tag = Mockery::mock(Tag::class)->makePartial();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($tag);

        $this->repository->shouldReceive('getPagesByTagId')
            ->with(1)
            ->once()
            ->andReturn(collect([Mockery::mock()]));

        $result = $this->service->handle([1]);

        $this->assertCount(0, $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('associated pages', $result['failed'][0]['reason']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

}