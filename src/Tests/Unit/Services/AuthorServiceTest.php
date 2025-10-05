<?php

namespace App\Tests\Unit\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Models\Author;
use App\Models\Page;
use App\Repositories\AuthorRepository;
use App\Services\AuthorService;
use App\Services\ImageUploadService;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthorServiceTest extends TestCase
{
    private $authorRepository;
    private $imageUploadService;
    private $database;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorRepository = Mockery::mock(AuthorRepository::class);
        $this->imageUploadService = Mockery::mock(ImageUploadService::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new AuthorService(
            $this->authorRepository,
            $this->imageUploadService,
            $this->database
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetAllAuthorsReturnsCollection()
    {
        $author = $this->createMockAuthor(1, 'Author 1');

        $author->shouldReceive('orderBy')
            //->with('name', 'asc')
            ->andReturnSelf();
        $author->shouldReceive('get')
            ->andReturn(collect([]));

        $result = $this->service->getAllAuthors();

        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
    }

    public function testCreateAuthorGeneratesSlugWhenNotProvided()
    {
        $data = ['name' => 'John Doe', 'email' => 'john@example.com'];

        $mockedAuthor = Mockery::mock(Author::class);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        // Str::slug will call findBySlug internally
        $this->authorRepository->shouldReceive('findBySlug')
            ->with('john-doe')
            ->once()
            ->andReturn(null);

        $this->authorRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($arg) {
                return $arg['name'] === 'John Doe' && $arg['slug'] === 'john-doe';
            }))
            ->andReturn($mockedAuthor);

        $result = $this->service->createAuthor($data);

        $this->assertInstanceOf(Author::class, $result);
        $this->assertSame($mockedAuthor, $result);
    }


    public function testCreateAuthorUploadsAvatarWhenProvided()
    {
        $data = ['name' => 'John Doe'];
        $avatarFile = Mockery::mock(UploadedFile::class);
        $avatarFile->shouldReceive('isValid')->andReturn(true);

        // Mock the database transaction
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        // Mock the image upload service
        $this->imageUploadService->shouldReceive('upload')
            ->with($avatarFile)
            ->once()
            ->andReturn('/uploads/avatar.jpg');

        // Mock repository to simulate no slug conflict
        $this->authorRepository->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        // Mock repository create() to assert avatar is set
        $mockedAuthor = Mockery::mock(Author::class);
        $this->authorRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($arg) =>
                isset($arg['avatar']) && $arg['avatar'] === '/uploads/avatar.jpg'
            ))
            ->andReturn($mockedAuthor);

        // Call the service
        $result = $this->service->createAuthor($data, $avatarFile);

        // Assert returned object
        $this->assertInstanceOf(Author::class, $result);
        $this->assertSame($mockedAuthor, $result);
    }

    public function testUpdateAuthorRegeneratesSlugWhenNameChanged()
    {
        // Use a partial mock so setAttribute() works
        $author = Mockery::mock(Author::class)->makePartial();
        $author->name = 'Old Name';
        $author->slug = 'old-name';
        $author->avatar = null;

        // Mock database transaction
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        // Mock repository to find the existing author
        $this->authorRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($author);

        // Mock repository to check for slug conflicts
        $this->authorRepository->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        // Mock repository update() and assert that slug is regenerated
        $this->authorRepository->shouldReceive('update')
            ->with(1, Mockery::on(fn($data) => isset($data['slug'])))
            ->once()
            ->andReturn($author);

        // Call the service
        $result = $this->service->updateAuthor(1, ['name' => 'New Name']);

        // Assert returned object
        $this->assertInstanceOf(Author::class, $result);
    }

    public function testDeleteAuthorThrowsExceptionWhenPagesExist()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Cannot delete author. It has 1 associated page(s). Please reassign or delete the associated pages first.'
        );

        $author = Mockery::mock(Author::class)->makePartial();

        // ❌ Remove this — transaction is not called in this case
        $this->database->shouldNotReceive('transaction');

        $this->authorRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($author);

        $this->authorRepository->shouldReceive('getPagesByAuthorId')
            ->with(1)
            ->once()
            ->andReturn(collect([Mockery::mock(Page::class)]));

        $this->service->delete(1);
    }

    public function testMergeAuthorsReassignsPagesAndDeletesSource(): void
    {
        // Mock source and target authors
        $sourceAuthor = Mockery::mock(Author::class)->makePartial();
        $sourceAuthor->avatar = '/uploads/source.jpg';

        $targetAuthor = Mockery::mock(Author::class)->makePartial();

        // Mock page that belongs to source author
        $page = Mockery::mock(Page::class)->makePartial();
        $page->shouldReceive('save')->once();

        // Mock database transaction
        $this->database->shouldReceive('transaction')
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
        $result = $this->service->mergeAuthors(1, 2);

        // Assert the result
        $this->assertTrue($result);
    }


    public function testSearchAuthorsCallsRepository()
    {
        $this->authorRepository->shouldReceive('searchAuthors')
            ->with('John', 10)
            ->once()
            ->andReturn(new Collection([]));

        $result = $this->service->searchAuthors('John', 10);

        $this->assertInstanceOf(Collection::class, $result);
    }

    private function createMockAuthor($id, $name)
    {
        // Pass constructor arguments if Author::__construct requires them
        $author = Mockery::mock(Author::class, [[], $this->database])->makePartial();

        // Set properties directly
        $author->id = $id;
        $author->name = $name;

        // Allow fill() to be called safely
        $author->shouldReceive('fill')->andReturnSelf();

        // Stub toArray() for convenience
        $author->shouldReceive('toArray')->andReturn([
            'id' => $id,
            'name' => $name,
        ]);

        // Optionally, stub setAttribute if your model uses it
        $author->shouldReceive('setAttribute')->andReturnNull();

        return $author;
    }

    public function testItCanDeleteAuthorWithoutPages()
    {
        $authorId = 1;
        $author = Mockery::mock(Author::class);
        $pages = Mockery::mock();

        $this->authorRepository->shouldReceive('find')
            ->with($authorId)
            ->once()
            ->andReturn($author);

        $this->authorRepository->shouldReceive('getPagesByAuthorId')
            ->with($authorId)
            ->once()
            ->andReturn(collect([]));

        $author->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $result = $this->service->delete($authorId);

        $this->assertTrue($result);
    }

    public function testItThrowsExceptionWhenDeletingAuthorWithPagesWithoutReassignment()
    {
        $authorId = 1;
        $author = Mockery::mock(Author::class);
        $pages = Mockery::mock();

        $this->authorRepository->shouldReceive('find')
            ->with($authorId)
            ->once()
            ->andReturn($author);

        $this->authorRepository->shouldReceive('getPagesByAuthorId')
            ->with($authorId)
            ->once()
            ->andReturn(collect([Mockery::mock(Page::class)]));

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($authorId);
    }

    public function testItCanDeleteAuthorAndReassignPages()
    {
        $authorId = 1;
        $reassignAuthorId = 2;
        $author = Mockery::mock(Author::class);
        $reassignAuthor = Mockery::mock(Author::class);
        $pages = Mockery::mock();

        $this->authorRepository->shouldReceive('find')
            ->with($authorId)
            ->once()
            ->andReturn($author);

        $this->authorRepository->shouldReceive('find')
            ->with($reassignAuthorId)
            ->once()
            ->andReturn($reassignAuthor);

        $this->authorRepository->shouldReceive('getPagesByAuthorId')
            ->with($authorId)
            ->once()
            ->andReturn(collect([Mockery::mock(Page::class)]));

        // This is the missing piece:
        $author->shouldReceive('pages')
            ->once()
            ->andReturn($pages);

        $pages->shouldReceive('update')
            ->with(['author_id' => $reassignAuthorId])
            ->once();

        $author->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->delete($authorId, $reassignAuthorId);

        $this->assertTrue($result);
    }

    public function testItChecksIfAuthorIsDeletable()
    {
        $authorId = 1;
        $author = Mockery::mock(Author::class);
        $pages = Mockery::mock();

        $this->authorRepository->shouldReceive('find')
            ->with($authorId)
            ->once()
            ->andReturn($author);

        $author->shouldReceive('pages')
            ->once()
            ->andReturn($pages);

        $pages->shouldReceive('count')
            ->once()
            ->andReturn(0);

        $result = $this->service->checkDeletable($authorId);

        $this->assertTrue($result['can_delete']);
    }

    public function testItGetsAlternativeAuthors()
    {
        $authorId = 1;
        $alternatives = new Collection([
            Mockery::mock(Author::class),
            Mockery::mock(Author::class)
        ]);

        $this->authorRepository->shouldReceive('getAlternatives')
            ->with($authorId)
            ->once()
            ->andReturn($alternatives);

        $result = $this->service->getAlternativeAuthors($authorId);

        $this->assertCount(2, $result);
    }
}