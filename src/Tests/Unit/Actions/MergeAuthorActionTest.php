<?php

namespace App\Tests\Unit\Actions;

use App\Actions\MergeAuthor;
use App\Framework\Database\Database;
use App\Models\Author;
use App\Models\Page;
use App\Repositories\Cms\AuthorRepository;
use App\Services\Cms\ImageUploadService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class MergeAuthorActionTest extends FunctionalTestCase
{
    use HasSiteHistory;

    private $authorRepository;
    private $imageUploadService;
    private $databaseMock;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorRepository = Mockery::mock(AuthorRepository::class);
        $this->imageUploadService = Mockery::mock(ImageUploadService::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new MergeAuthor(
            $this->authorRepository,
            $this->imageUploadService,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testMergeAuthorsReassignsPagesAndDeletesSource(): void
    {
        $sourceAuthor = Mockery::mock(Author::class)->makePartial();
        $sourceAuthor->avatar = '/uploads/source.jpg';
        $sourceAuthor->id = 1;

        $targetAuthor = Mockery::mock(Author::class)->makePartial();
        $targetAuthor->id = 2;

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 10;
        $page->shouldReceive('save')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->authorRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($sourceAuthor);

        $this->authorRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($targetAuthor);

        $this->authorRepository->shouldReceive('getPagesByAuthorId')
            ->with(1)
            ->once()
            ->andReturn(collect([$page]));

        $this->setCloneHistoryExpectations($sourceAuthor, $targetAuthor, 1, 2, 'merged');

        $this->imageUploadService->shouldReceive('delete')
            ->with('/uploads/source.jpg')
            ->once();

        $this->authorRepository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['source_author_id']);
        $this->assertEquals(2, $result['target_author_id']);
    }

    public function testMergeAuthorsReturnsDetailedResults(): void
    {
        $sourceAuthor = Mockery::mock(Author::class)->makePartial();
        $sourceAuthor->avatar = '/uploads/source.jpg';
        $sourceAuthor->id = 1;

        $targetAuthor = Mockery::mock(Author::class)->makePartial();
        $targetAuthor->id = 2;

        $page1 = Mockery::mock(Page::class)->makePartial();
        $page1->id = 10;
        $page1->shouldReceive('save')->once();

        $page2 = Mockery::mock(Page::class)->makePartial();
        $page2->id = 11;
        $page2->shouldReceive('save')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->authorRepository->shouldReceive('find')->with(1)->andReturn($sourceAuthor);
        $this->authorRepository->shouldReceive('find')->with(2)->andReturn($targetAuthor);
        $this->authorRepository->shouldReceive('getPagesByAuthorId')->with(1)
            ->andReturn(collect([$page1, $page2]));

        $this->setCloneHistoryExpectations($sourceAuthor, $targetAuthor, 1, 2, 'merged');
        $this->imageUploadService->shouldReceive('delete')->once();
        $this->authorRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertArrayHasKey('results', $result);
        $this->assertContains('pages_reassigned', $result['results']['success']);
        $this->assertContains('merge_history', $result['results']['success']);
        $this->assertContains('avatar_deleted', $result['results']['success']);
        $this->assertContains('author_deleted', $result['results']['success']);
        $this->assertEquals(2, $result['results']['pages_reassigned']);
    }

    public function testMergeAuthorsWithoutAvatar(): void
    {
        $sourceAuthor = Mockery::mock(Author::class)->makePartial();
        $sourceAuthor->avatar = null;
        $sourceAuthor->id = 1;

        $targetAuthor = Mockery::mock(Author::class)->makePartial();
        $targetAuthor->id = 2;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->authorRepository->shouldReceive('find')->with(1)->andReturn($sourceAuthor);
        $this->authorRepository->shouldReceive('find')->with(2)->andReturn($targetAuthor);
        $this->authorRepository->shouldReceive('getPagesByAuthorId')->with(1)
            ->andReturn(collect([]));

        $this->setCloneHistoryExpectations($sourceAuthor, $targetAuthor, 1, 2, 'merged');
        $this->imageUploadService->shouldReceive('delete')->never();
        $this->authorRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertTrue($result['success']);
        $this->assertNotContains('avatar_deleted', $result['results']['success']);
    }

    public function testMergeAuthorsThrowsExceptionForSameAuthor(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot merge an author with itself');

        $this->service->handle(1, 1);
    }

    public function testMergeAuthorsThrowsExceptionWhenSourceNotFound(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->authorRepository->shouldReceive('find')->with(999)->andReturn(null);
        $this->authorRepository->shouldReceive('find')->with(2)->andReturn(new Author());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('One or both authors not found');

        $this->service->handle(999, 2);
    }

    public function testMergeAuthorsThrowsExceptionWhenTargetNotFound(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->authorRepository->shouldReceive('find')->with(1)->andReturn(new Author());
        $this->authorRepository->shouldReceive('find')->with(999)->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('One or both authors not found');

        $this->service->handle(1, 999);
    }

    public function testMergeAuthorsHandlesPageReassignmentFailure(): void
    {
        $sourceAuthor = Mockery::mock(Author::class)->makePartial();
        $sourceAuthor->avatar = null;
        $sourceAuthor->id = 1;

        $targetAuthor = Mockery::mock(Author::class)->makePartial();
        $targetAuthor->id = 2;

        $page1 = Mockery::mock(Page::class)->makePartial();
        $page1->id = 10;
        $page1->shouldReceive('save')->once();

        $page2 = Mockery::mock(Page::class)->makePartial();
        $page2->id = 11;
        $page2->shouldReceive('save')->once()->andThrow(new \Exception('Save failed'));

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->authorRepository->shouldReceive('find')->with(1)->andReturn($sourceAuthor);
        $this->authorRepository->shouldReceive('find')->with(2)->andReturn($targetAuthor);
        $this->authorRepository->shouldReceive('getPagesByAuthorId')->with(1)
            ->andReturn(collect([$page1, $page2]));

        $this->setCloneHistoryExpectations($sourceAuthor, $targetAuthor, 1, 2, 'merged');
        $this->authorRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['results']['pages_reassigned']);
        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('reassign_page', $result['results']['failed'][0]['operation']);
        $this->assertEquals(11, $result['results']['failed'][0]['page_id']);
    }

    public function testMergeAuthorsHandlesAvatarDeletionFailure(): void
    {
        $sourceAuthor = Mockery::mock(Author::class)->makePartial();
        $sourceAuthor->avatar = '/uploads/source.jpg';
        $sourceAuthor->id = 1;

        $targetAuthor = Mockery::mock(Author::class)->makePartial();
        $targetAuthor->id = 2;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->authorRepository->shouldReceive('find')->with(1)->andReturn($sourceAuthor);
        $this->authorRepository->shouldReceive('find')->with(2)->andReturn($targetAuthor);
        $this->authorRepository->shouldReceive('getPagesByAuthorId')->with(1)
            ->andReturn(collect([]));

        $this->setCloneHistoryExpectations($sourceAuthor, $targetAuthor, 1, 2, 'merged');

        $this->imageUploadService->shouldReceive('delete')
            ->with('/uploads/source.jpg')
            ->once()
            ->andThrow(new \Exception('File not found'));

        $this->authorRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->handle(1, 2);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('delete_avatar', $result['results']['failed'][0]['operation']);
    }

    public function testMergeAuthorsRollsBackOnDeleteFailure(): void
    {
        $sourceAuthor = Mockery::mock(Author::class)->makePartial();
        $sourceAuthor->id = 1;

        $targetAuthor = Mockery::mock(Author::class)->makePartial();
        $targetAuthor->id = 2;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                try {
                    return $callback();
                } catch (\Exception $e) {
                    throw $e;
                }
            });

        $this->authorRepository->shouldReceive('find')->with(1)->andReturn($sourceAuthor);
        $this->authorRepository->shouldReceive('find')->with(2)->andReturn($targetAuthor);
        $this->authorRepository->shouldReceive('getPagesByAuthorId')->with(1)
            ->andReturn(collect([]));

        $this->setCloneHistoryExpectations($sourceAuthor, $targetAuthor, 1, 2, 'merged');

        $this->authorRepository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andThrow(new \Exception('Delete failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Delete failed');

        $this->service->handle(1, 2);
    }

}