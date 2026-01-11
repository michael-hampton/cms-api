<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkDeleteAuthor;
use App\Framework\Database\Database;
use App\Models\Author;
use App\Models\Page;
use App\Repositories\Cms\AuthorRepository;
use App\Services\Cms\ImageUploadService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkDeleteAuthorActionTest extends FunctionalTestCase
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

        $this->service = new BulkDeleteAuthor(
            $this->authorRepository,
            $this->imageUploadService,
            $this->databaseMock
        );
    }

    public function testBulkDeleteSuccessfully(): void
    {
        $author1 = Mockery::mock(Author::class)->makePartial();
        $author1->avatar = null;

        $author2 = Mockery::mock(Author::class)->makePartial();
        $author2->avatar = null;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->authorRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($author1);

        $this->authorRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($author2);

        $this->authorRepository->shouldReceive('getPagesByAuthorId')
            ->twice()
            ->andReturn(collect([]));

        $author1->shouldReceive('delete')->once()->andReturn(true);
        $author2->shouldReceive('delete')->once()->andReturn(true);

        $result = $this->service->handle([1, 2]);

        $this->assertCount(2, $result['deleted']);
        $this->assertCount(0, $result['failed']);
    }

    public function testBulkDeleteFailsWhenPagesExist(): void
    {
        $author1 = Mockery::mock(Author::class)->makePartial();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->authorRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($author1);

        $this->authorRepository->shouldReceive('getPagesByAuthorId')
            ->with(1)
            ->once()
            ->andReturn(collect([Mockery::mock(Page::class)]));

        $result = $this->service->handle([1]);

        $this->assertCount(0, $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('associated pages', $result['failed'][0]['reason']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}