<?php

namespace App\Tests\Unit\Actions;

use App\Actions\MergeTag;
use App\Framework\Database\Database;
use App\Models\Tag;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Services\TagService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class MergeTagsActionTest extends FunctionalTestCase
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
        $this->service = new MergeTag($this->repository);
    }

    public function testMergeTagsSuccessfully(): void
    {
        $fromTagId = 1;
        $toTagId = 2;
        $fromTag = Mockery::mock(Tag::class)->makePartial();
        $fromTag->id = $fromTagId;
        $toTag = Mockery::mock(Tag::class)->makePartial();
        $toTag->id = $toTagId;

        $this->setCloneHistoryExpectations($fromTag, $toTag, $fromTagId, $toTagId, 'merged');

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

        $result = $this->service->handle($fromTagId, $toTagId);

        $this->assertTrue($result);
    }

    public function testMergeTagsThrowsExceptionForSameTag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot merge a tag with itself');

        $this->service->handle(1, 1);
    }

    public function testMergeTagsThrowsExceptionWhenSourceNotFound(): void
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Source tag not found');

        $this->service->handle(999, 2);
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

        $this->service->handle(1, 999);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}