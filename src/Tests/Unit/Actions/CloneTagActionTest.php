<?php

namespace App\Tests\Unit\Actions;

use App\Actions\CloneTag;
use App\Framework\Database\Database;
use App\Models\Tag;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class CloneTagActionTest extends FunctionalTestCase
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
        $this->service = new CloneTag($this->databaseMock, $this->repository, $this->pageRepository);
    }

    public function testDuplicateTagSuccessfully(): void
    {
        $originalTag = Mockery::mock(Tag::class)->makePartial();
        $originalTag->id = 1;
        $originalTag->name = 'PHP';
        $originalTag->description = 'PHP related';
        $originalTag->status = 'inactive';
        $originalTag->seo_title = 'PHP SEO Title';
        $originalTag->seo_description = 'PHP SEO Description';
        $originalTag->site_id = 1;

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

        $newTag = Mockery::mock(Tag::class)->makePartial();
        $newTag->id = 2;

        $this->setCloneHistoryExpectations($originalTag, $newTag, 1, 2);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with([
                'name' => 'PHP (Copy)',
                'description' => 'PHP related',
                'status' => 'inactive',
                'seo_title' => 'PHP SEO Title',
                'seo_description' => 'PHP SEO Description',
                'slug' => 'php-copy',
                'site_id' => 1,
                'canonical_url' => null,
                'no_index' => false,
            ])
            ->andReturn($newTag);

        $result = $this->service->handle(1, null, 1);

        $this->assertInstanceOf(Tag::class, $result['tag']);
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

        $this->service->handle(999);
    }

    public function testCloneTagReturnsDetailedResults()
    {
        $originalTag = Mockery::mock(Tag::class)->makePartial();
        $originalTag->id = 1;
        $originalTag->name = 'PHP';
        $originalTag->site_id = 1;

        $newTag = Mockery::mock(Tag::class)->makePartial();
        $newTag->id = 2;
        $newTag->site_id = 1;

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('find')->with(1)->andReturn($originalTag);
        $this->repository->shouldReceive('findBySlug')->andReturn(null);
        $this->repository->shouldReceive('create')->andReturn($newTag);
        $this->setCloneHistoryExpectations($originalTag, $newTag, 1, 2);

        $result = $this->service->handle(1, null, $this->siteId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tag', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('original_tag_id', $result);
        $this->assertArrayHasKey('cross_site', $result);
        $this->assertFalse($result['cross_site']);
        $this->assertContains('tag_created', $result['results']['success']);
        $this->assertContains('clone_history', $result['results']['success']);
    }

    public function testCloneTagCrossSiteTracking()
    {
        $originalTag = Mockery::mock(Tag::class)->makePartial();
        $originalTag->id = 1;
        $originalTag->name = 'PHP';
        $originalTag->site_id = 1;

        $newTag = Mockery::mock(Tag::class)->makePartial();
        $newTag->id = 2;
        $newTag->site_id = 2;

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('find')->with(1)->andReturn($originalTag);
        $this->repository->shouldReceive('findBySlug')->andReturn(null);
        $this->repository->shouldReceive('create')->andReturn($newTag);
        $this->setCloneHistoryExpectations($originalTag, $newTag, 1, 2, 'cloned', 1, 2);

        $result = $this->service->handle(1, null, 2);

        $this->assertTrue($result['cross_site']);
        $this->assertContains('cross_site_clone_history', $result['results']['success']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}