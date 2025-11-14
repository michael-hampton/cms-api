<?php

namespace App\Tests\Unit\Actions;

use App\Actions\CloneAuthor;
use App\Framework\Database\Database;
use App\Models\Author;
use App\Repositories\AuthorRepository;
use App\Services\AuthorService;
use App\Services\ImageUploadService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class CloneAuthorActionTest extends FunctionalTestCase
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

        $this->service = new CloneAuthor(
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


    public function testDuplicateAuthorWithCustomName(): void
    {
        $originalAuthor = new Author([
            'id' => 1,
            'name' => 'John Doe',
            'bio' => 'Author bio',
            'status' => 'active',
            'slug' => 'john-doe'
        ]);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->authorRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalAuthor);

        $this->authorRepository
            ->shouldReceive('findBySlug')
            ->with('jane-smith')
            ->once()
            ->andReturn(null);

        $newAuthor = new Author([
            'id' => 2,
            'name' => 'Jane Smith',
            'slug' => 'jane-smith'
        ]);

        $this->authorRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newAuthor);

        $result = $this->service->handle(1, 'Jane Smith');

        $this->assertEquals('Jane Smith', $result->name);
    }

    public function testDuplicateAuthorThrowsExceptionWhenNotFound(): void
    {
        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->authorRepository
            ->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Author not found');

        $this->service->handle(999);
    }

    public function testDuplicateAuthorHandlesAvatarDuplicationFailure(): void
    {
        $originalAuthor = Mockery::mock(Author::class)->makePartial();
        $originalAuthor->id = 1;
        $originalAuthor->name = 'John Doe';
        $originalAuthor->avatar = 'avatars/john.jpg';

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->authorRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalAuthor);

        $this->imageUploadService
            ->shouldReceive('duplicate')
            ->once()
            ->andThrow(new \Exception('File not found'));

        $this->authorRepository
            ->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        $newAuthor = Mockery::mock(Author::class)->makePartial();
        $newAuthor->id = 2;

        $this->authorRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['avatar'] === null;
            }))
            ->andReturn($newAuthor);

        $this->setCloneHistoryExpectations($originalAuthor, $newAuthor, 1, 2);

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Author::class, $result);
    }
}