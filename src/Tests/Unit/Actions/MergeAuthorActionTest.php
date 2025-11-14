<?php

namespace App\Tests\Unit\Actions;

use App\Actions\MergeAuthor;
use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Models\Author;
use App\Models\Page;
use App\Repositories\AuthorRepository;
use App\Services\AuthorService;
use App\Services\ImageUploadService;
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
        // Mock source and target authors
        $sourceAuthor = Mockery::mock(Author::class)->makePartial();
        $sourceAuthor->avatar = '/uploads/source.jpg';
        $sourceAuthor->id = 1;

        $targetAuthor = Mockery::mock(Author::class)->makePartial();
        $targetAuthor->id = 2;

        // Mock page that belongs to source author
        $page = Mockery::mock(Page::class)->makePartial();
        $page->shouldReceive('save')->once();

        // Mock database transaction
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        // Author repository: find source and target
        $this->authorRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($sourceAuthor);

        $this->authorRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($targetAuthor);

        // Page repository: return pages belonging to source author
        $this->authorRepository->shouldReceive('getPagesByAuthorId')
            ->with(1)
            ->once()
            ->andReturn(collect([$page]));

        $this->setCloneHistoryExpectations($sourceAuthor, $targetAuthor, 1, 2, 'merged');

        // Image deletion for source author avatar
        $this->imageUploadService->shouldReceive('delete')
            ->with('/uploads/source.jpg')
            ->once();

        // Author repository: delete source author
        $this->authorRepository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        // Call the service
        $result = $this->service->handle(1, 2);

        // Assert the result
        $this->assertTrue($result);
    }
}