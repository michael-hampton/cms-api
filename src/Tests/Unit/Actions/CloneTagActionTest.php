<?php

namespace App\Tests\Unit\Actions;

use App\Actions\CloneTag;
use App\Framework\Database\Database;
use App\Models\Tag;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\TagService;
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

        $this->assertInstanceOf(Tag::class, $result);
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}