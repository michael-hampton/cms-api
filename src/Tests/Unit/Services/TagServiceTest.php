<?php

namespace App\Tests\Unit\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Category;
use App\Models\PageCategory;
use App\Models\PageTag;
use App\Models\Tag;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\TagService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class TagServiceTest extends FunctionalTestCase
{
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

    public function testDuplicateTagSuccessfully(): void
    {
        $originalTag = new Tag([
            'id' => 1,
            'name' => 'PHP',
            'description' => 'PHP related',
            'slug' => 'php',
            'seo_title' => 'PHP SEO Title',
            'seo_description' => 'PHP SEO Description',
            'no_index' => true,
            'canonical_url' => 'https://example.com/php'
        ]);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->repository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalTag);

        $this->repository
            ->shouldReceive('findBySlug')
            ->with('php-copy')
            ->once()
            ->andReturn(null);

        $newTag = new Tag([
            'id' => 2,
            'name' => 'PHP (Copy)',
            'slug' => 'php-copy',
            'seo_title' => 'PHP SEO Title',
            'seo_description' => 'PHP SEO Description',
            'no_index' => true,
            'canonical_url' => null
        ]);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'name' => 'PHP (Copy)',
                'description' => 'PHP related',
                'status' => 'inactive',
                'seo_title' => 'PHP SEO Title',
                'seo_description' => 'PHP SEO Description',
                'no_index' => true,
                'canonical_url' => NULL,
                'slug' => 'php-copy'
            ])
            ->andReturn($newTag);

        $result = $this->service->duplicateTag(1);

        $this->assertTrue($result);
    }

    public function testDuplicateTagThrowsExceptionWhenNotFound(): void
    {
        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->repository
            ->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tag not found');

        $this->service->duplicateTag(999);
    }
}