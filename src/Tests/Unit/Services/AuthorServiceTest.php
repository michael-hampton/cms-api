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
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class AuthorServiceTest extends FunctionalTestCase
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

        $this->service = new AuthorService(
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

    public function testGetAllAuthorsReturnsCollection()
    {
        $author = Mockery::mock(Author::class)->makePartial();

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

        $this->databaseMock->shouldReceive('transaction')
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

        $result = $this->service->createAuthor($data, 1);

        $this->assertInstanceOf(Author::class, $result);
        $this->assertSame($mockedAuthor, $result);
    }


    public function testCreateAuthorUploadsAvatarWhenProvided()
    {
        $data = ['name' => 'John Doe'];
        $avatarFile = Mockery::mock(UploadedFile::class);
        $avatarFile->shouldReceive('isValid')->andReturn(true);

        // Mock the database transaction
        $this->databaseMock->shouldReceive('transaction')
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
        $result = $this->service->createAuthor($data, 1, $avatarFile);

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
        $this->databaseMock->shouldReceive('transaction')
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
        $this->databaseMock->shouldNotReceive('transaction');

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


    public function testSearchAuthorsCallsRepository()
    {
        $this->authorRepository->shouldReceive('searchAuthors')
            ->with('John', 10)
            ->once()
            ->andReturn(new Collection([]));

        $result = $this->service->searchAuthors('John', 10);

        $this->assertInstanceOf(Collection::class, $result);
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

        // Mock a page that will be reassigned
        $page = Mockery::mock(Page::class)->makePartial();
        $page->shouldReceive('save')->once();

        $this->authorRepository->shouldReceive('find')
            ->with($authorId)
            ->once()
            ->andReturn($author);

        $this->authorRepository->shouldReceive('find')
            ->with($reassignAuthorId)
            ->once()
            ->andReturn($reassignAuthor);

        // Called twice: once for count check, once inside transaction
        $this->authorRepository->shouldReceive('getPagesByAuthorId')
            ->with($authorId)
            ->twice()
            ->andReturn(collect([$page]));

        $author->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
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

    public function testCreateAuthorWithExpertiseFields()
    {
        $data = [
            'name' => 'John Doe',
            'expertise' => 'Web development',
            'location' => ['New York'],
            'education' => ['BS CS'],
            'awards' => ['Award 1'],
            'seniority_date' => '2020-01-01',
            'is_active' => true
        ];

        $mockedAuthor = Mockery::mock(Author::class);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->authorRepository->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        $this->authorRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['expertise'] === 'Web development' &&
                    $arg['location'] === ['New York'] &&
                    is_bool($arg['is_active']);
            }))
            ->andReturn($mockedAuthor);

        $result = $this->service->createAuthor($data, 1);

        $this->assertInstanceOf(Author::class, $result);
    }
}