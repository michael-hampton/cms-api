<?php

namespace App\Tests\Unit\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Tag;
use App\Repositories\TagRepository;
use App\Services\TagService;
use Mockery;
use PHPUnit\Framework\TestCase;

class TagServiceTest extends TestCase
{
    protected $repository;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(TagRepository::class);
        $this->service = new TagService($this->database, $this->repository);
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

        $tag->shouldReceive('pages')
            ->once()
            ->andReturn($pages);

        $pages->shouldReceive('count')
            ->once()
            ->andReturn(0);

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

        $tag->shouldReceive('pages')
            ->once()
            ->andReturn($pages);

        $pages->shouldReceive('count')
            ->once()
            ->andReturn(2);

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($tagId);
    }

    public function testItCanDeleteTagAndReassignPages()
    {
        $tagId = 1;
        $reassignTagId = 2;
        $tag = Mockery::mock(Tag::class)->makePartial();
        $tag->id = $tagId;
        $reassignTag = Mockery::mock(Tag::class);
        $pages = Mockery::mock();
        $pagesCollection = new Collection();

        $this->repository->shouldReceive('find')
            ->with($tagId)
            ->once()
            ->andReturn($tag);

        $this->repository->shouldReceive('find')
            ->with($reassignTagId)
            ->once()
            ->andReturn($reassignTag);

        $tag->shouldReceive('pages')
            ->twice()
            ->andReturn($pages);

        $pages->shouldReceive('count')
            ->once()
            ->andReturn(1);

        $pages->shouldReceive('get')
            ->once()
            ->andReturn($pagesCollection);

        $tag->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->delete($tagId, $reassignTagId);

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