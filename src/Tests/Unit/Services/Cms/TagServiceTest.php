<?php

namespace App\Tests\Unit\Services\Cms;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\PageTag;
use App\Models\Tag;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\TagService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class TagServiceTest extends FunctionalTestCase
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
        $this->service = new TagService($this->databaseMock, $this->repository, $this->pageRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testItCanDeleteTagWithoutPages()
    {
        $tagId = 1;
        $tag = Mockery::mock(Tag::class)->makePartial();
        $pages = Mockery::mock();

        $this->repository->shouldReceive('find')
            ->with($tagId)
            ->once()
            ->andReturn($tag);

       $collection = Mockery::mock(Collection::class);
       $this->repository->shouldReceive('getPagesByTagId')->with($tagId)->once()->andReturn($collection);
       $collection->shouldReceive('count')->once()->andReturn(0);

        $tag->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $result = $this->service->delete($tagId);

        $this->assertTrue($result);
    }

    public function testItThrowsExceptionWhenDeletingTagWithPagesWithoutReassignment()
    {
        $tagId = 1;
        $tag = Mockery::mock(Tag::class);
        $pages = Mockery::mock();

        $this->repository->shouldReceive('find')
            ->with($tagId)
            ->once()
            ->andReturn($tag);

        $collection = Mockery::mock(Collection::class);
        $this->repository->shouldReceive('getPagesByTagId')->with($tagId)->once()->andReturn($collection);
        $collection->shouldReceive('count')->once()->andReturn(1);

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($tagId);
    }

    public function testItCanDeleteTagAndReassignPages()
    {
        $authorId = 1;
        $reassignAuthorId = 2;
        $author = Mockery::mock(Tag::class);
        $reassignAuthor = Mockery::mock(Tag::class);

        // Mock a page that will be reassigned
        $page = Mockery::mock(PageTag::class)->makePartial();
        $page->id = 1;

        $this->repository->shouldReceive('find')
            ->with($authorId)
            ->once()
            ->andReturn($author);

        $this->repository->shouldReceive('find')
            ->with($reassignAuthorId)
            ->once()
            ->andReturn($reassignAuthor);

        // Called twice: once for count check, once inside transaction
        $this->repository->shouldReceive('getPagesByTagId')
            ->with($authorId)
            ->twice()
            ->andReturn(collect([$page]));

        $this->pageRepository->shouldReceive('syncTags')
            ->once()
            ->with($page->id, $reassignAuthorId);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->delete($authorId, $reassignAuthorId);

        $this->assertTrue($result);
    }

    public function testItGetsAlternativeTags()
    {
        $tagId = 1;
        $alternatives = new Collection([
            Mockery::mock(Tag::class)
        ]);

        $this->repository->shouldReceive('getAlternatives')
            ->with($tagId)
            ->once()
            ->andReturn($alternatives);

        $result = $this->service->getAlternativeTags($tagId);

        $this->assertCount(1, $result);
    }
}