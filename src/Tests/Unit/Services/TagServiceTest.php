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
                'slug' => 'php-copy',
                'site_id' => 1
            ])
            ->andReturn($newTag);

        $result = $this->service->duplicateTag(1, null, 1);

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

    public function testMergeTagsSuccessfully(): void
    {
        $fromTagId = 1;
        $toTagId = 2;
        $fromTag = Mockery::mock(Tag::class);
        $toTag = Mockery::mock(Tag::class);

        $this->repository->shouldReceive('find')
            ->with($fromTagId)
            ->once()
            ->andReturn($fromTag);

        $this->repository->shouldReceive('find')
            ->with($toTagId)
            ->once()
            ->andReturn($toTag);

        $this->repository->shouldReceive('mergeTags')
            ->with($fromTagId, $toTagId)
            ->once()
            ->andReturn(true);

        $result = $this->service->mergeTags($fromTagId, $toTagId);

        $this->assertTrue($result);
    }

    public function testMergeTagsThrowsExceptionForSameTag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot merge a tag with itself');

        $this->service->mergeTags(1, 1);
    }

    public function testMergeTagsThrowsExceptionWhenSourceNotFound(): void
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Source tag not found');

        $this->service->mergeTags(999, 2);
    }

    public function testMergeTagsThrowsExceptionWhenTargetNotFound(): void
    {
        $fromTag = Mockery::mock(Tag::class);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($fromTag);

        $this->repository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Target tag not found');

        $this->service->mergeTags(1, 999);
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

        $result = $this->service->bulkDelete([1, 2]);

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

        $result = $this->service->bulkDelete([1]);

        $this->assertCount(0, $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('associated pages', $result['failed'][0]['reason']);
    }
}