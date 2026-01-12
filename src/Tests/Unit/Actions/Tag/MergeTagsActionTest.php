<?php

namespace App\Tests\Unit\Actions\Tag;

use App\Actions\Tag\MergeTag;
use App\Framework\Database\Database;
use App\Models\Tag;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\TagRepository;
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

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['source_tag_id']);
        $this->assertEquals(2, $result['target_tag_id']);
    }

    public function testMergeTagsReturnsDetailedResults(): void
    {
        $fromTag = Mockery::mock(Tag::class)->makePartial();
        $fromTag->id = 1;
        $toTag = Mockery::mock(Tag::class)->makePartial();
        $toTag->id = 2;

        $this->setCloneHistoryExpectations($fromTag, $toTag, 1, 2, 'merged');

        $this->repository->shouldReceive('find')->with(1)->andReturn($fromTag);
        $this->repository->shouldReceive('find')->with(2)->andReturn($toTag);
        $this->repository->shouldReceive('mergeTags')->with(1, 2)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('success', $result['results']);
        $this->assertArrayHasKey('failed', $result['results']);
        $this->assertContains('merge_history', $result['results']['success']);
        $this->assertContains('tags_merged', $result['results']['success']);
        $this->assertCount(0, $result['results']['failed']);
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

    public function testMergeTagsHandlesMergeFailure(): void
    {
        $fromTag = Mockery::mock(Tag::class)->makePartial();
        $fromTag->id = 1;
        $toTag = Mockery::mock(Tag::class)->makePartial();
        $toTag->id = 2;

        $this->setCloneHistoryExpectations($fromTag, $toTag, 1, 2, 'merged');

        $this->repository->shouldReceive('find')->with(1)->andReturn($fromTag);
        $this->repository->shouldReceive('find')->with(2)->andReturn($toTag);
        $this->repository->shouldReceive('mergeTags')->with(1, 2)->andReturn(false);

        $result = $this->service->handle(1, 2);

        $this->assertFalse($result['success']);
        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('merge_tags', $result['results']['failed'][0]['operation']);
    }

    public function testMergeTagsHandlesMergeException(): void
    {
        $fromTag = Mockery::mock(Tag::class)->makePartial();
        $fromTag->id = 1;
        $toTag = Mockery::mock(Tag::class)->makePartial();
        $toTag->id = 2;

        $this->setCloneHistoryExpectations($fromTag, $toTag, 1, 2, 'merged');

        $this->repository->shouldReceive('find')->with(1)->andReturn($fromTag);
        $this->repository->shouldReceive('find')->with(2)->andReturn($toTag);
        $this->repository->shouldReceive('mergeTags')
            ->with(1, 2)
            ->andThrow(new \Exception('Database error'));

        $result = $this->service->handle(1, 2);

        $this->assertFalse($result['success']);
        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('merge_tags', $result['results']['failed'][0]['operation']);
        $this->assertEquals('Database error', $result['results']['failed'][0]['error']);
    }

    public function testMergeTagsHandlesMergeHistoryFailure(): void
    {
        $fromTag = Mockery::mock(Tag::class)->makePartial();
        $fromTag->id = 1;
        // Don't expect this to be called since toTag throws first
        $fromTag->shouldReceive('addCloneRecord')->never();

        $toTag = Mockery::mock(Tag::class)->makePartial();
        $toTag->id = 2;
        $toTag->shouldReceive('addCloneRecord')
            ->once() // This is called first and throws
            ->andThrow(new \Exception('History write failed'));

        $this->repository->shouldReceive('find')->with(1)->andReturn($fromTag);
        $this->repository->shouldReceive('find')->with(2)->andReturn($toTag);
        $this->repository->shouldReceive('mergeTags')->with(1, 2)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertTrue($result['success']); // Merge still succeeds
        $this->assertCount(1, $result['results']['failed']); // Only 1 failure recorded
        $this->assertEquals('merge_history', $result['results']['failed'][0]['operation']);
        $this->assertEquals('History write failed', $result['results']['failed'][0]['error']);
    }

    public function testMergeTagsSuccessfullyWithAllOperations(): void
    {
        $fromTag = Mockery::mock(Tag::class)->makePartial();
        $fromTag->id = 1;
        $toTag = Mockery::mock(Tag::class)->makePartial();
        $toTag->id = 2;

        $this->setCloneHistoryExpectations($fromTag, $toTag, 1, 2, 'merged');

        $this->repository->shouldReceive('find')->with(1)->andReturn($fromTag);
        $this->repository->shouldReceive('find')->with(2)->andReturn($toTag);
        $this->repository->shouldReceive('mergeTags')->with(1, 2)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['results']['success']);
        $this->assertContains('merge_history', $result['results']['success']);
        $this->assertContains('tags_merged', $result['results']['success']);
        $this->assertCount(0, $result['results']['failed']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}