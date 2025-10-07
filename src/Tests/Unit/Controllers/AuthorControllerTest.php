<?php

namespace App\Tests\Unit\Controllers;

use App\Controllers\AuthorController;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Collection;
use App\Models\Author;
use App\Models\Tag;
use App\Repositories\AuthorRepository;
use App\Requests\CreateAuthorRequest;
use App\Requests\UpdateAuthorRequest;
use App\Search\PaginatedResult;
use App\Services\AuthorService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthorControllerTest extends FunctionalTestCase
{
    private $authorService;
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorService = Mockery::mock(AuthorService::class);
        $this->authorRepository = Mockery::mock(AuthorRepository::class);
        $this->controller = new AuthorController($this->authorService, $this->authorRepository);;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testIndexAppliesSearchQuery()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => 'john',
                    'search' => null,
                    'sort_by' => null,
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $authors = collect([
            $this->createMockAuthor(1, 'Tag 1'),
            $this->createMockAuthor(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($authors->toArray(), $authors->count(), 1, 20);

        $this->authorRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getSearchQuery() === 'john';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);;

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['pagination']['total']);
        $this->assertEquals('Tag 1', $data['items'][0]['name']);
    }

    public function testIndexAppliesSortByEmail()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => null,
                    'search' => null,
                    'sort_by' => 'email',
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $authors = collect([
            $this->createMockAuthor(1, 'Tag 1'),
            $this->createMockAuthor(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($authors->toArray(), $authors->count(), 1, 20);

        $this->authorRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getSortBy() === 'email'
                    && $criteria->getSortOrder() === 'asc';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['pagination']['total']);
        $this->assertEquals('Tag 1', $data['items'][0]['name']);
    }

    public function testIndexAppliesSortByName()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => null,
                    'search' => null,
                    'sort_by' => 'name',
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $authors = collect([
            $this->createMockAuthor(1, 'Tag 1'),
            $this->createMockAuthor(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($authors->toArray(), $authors->count(), 1, 20);

        $this->authorRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getSortBy() === 'name'
                    && $criteria->getSortOrder() === 'asc';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);;

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['pagination']['total']);
        $this->assertEquals('Tag 1', $data['items'][0]['name']);
    }

    public function testIndexHandlesNoFilters()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => null,
                    'search' => ['site' => $this->siteSlug],
                    'sort_by' => null,
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $authors = collect([
            $this->createMockAuthor(1, 'Tag 1'),
            $this->createMockAuthor(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($authors->toArray(), $authors->count(), 1, 20);

        $this->authorRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return empty($criteria->getFilters());
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['pagination']['total']);
        $this->assertEquals('Tag 1', $data['items'][0]['name']);
    }

    public function testStoreCreatesAuthorSuccessfully()
    {
        $request = Mockery::mock(CreateAuthorRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'name' => 'New Author',
            'email' => 'author@example.com',
            'site_id' => 1
        ]);
        $request->shouldReceive('get')->with('site_id')->andReturn($this->siteId);
        $request->shouldReceive('hasFile')->with('avatar')->andReturn(false);
        $request->shouldReceive('file')->never();

        $author = Mockery::mock(Author::class);
        $author->shouldReceive('toArray')->andReturn([
            'id' => 1,
            'name' => 'New Author',
            'email' => 'author@example.com'
        ]);

        $this->authorService->shouldReceive('createAuthor')
            ->once()
            ->andReturn($author);

        $response = $this->controller->store($request, $this->siteSlug);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testShowReturnsAuthorByIdWhenNumeric()
    {
        $author = Mockery::mock(Author::class);
        $author->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Author']);
        $author->shouldReceive('relationLoaded')->with('pages')->andReturn(false);

        $this->authorService->shouldReceive('getAuthorById')
            ->with(1)
            ->once()
            ->andReturn($author);

        $response = $this->controller->show(1);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturnsAuthorBySlugWhenString()
    {
        $author = Mockery::mock(Author::class);
        $author->shouldReceive('toArray')->andReturn(['id' => 1, 'slug' => 'author-slug']);
        $author->shouldReceive('relationLoaded')->with('pages')->andReturn(false);

        $this->authorService->shouldReceive('getAuthorBySlug')
            ->with('author-slug')
            ->once()
            ->andReturn($author);

        $response = $this->controller->show('author-slug');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturns404WhenAuthorNotFound()
    {
        $this->authorService->shouldReceive('getAuthorById')
            ->with(999)
            ->andReturn(null);

        $response = $this->controller->show(999);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateUpdatesAuthorSuccessfully()
    {
        $request = Mockery::mock(UpdateAuthorRequest::class);
        $request->shouldReceive('validated')->andReturn(['name' => 'Updated Name']);
        $request->shouldReceive('hasFile')->with('avatar')->andReturn(false);

        $author = Mockery::mock(Author::class);
        $author->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Updated Name']);

        $this->authorService->shouldReceive('updateAuthor')
            ->with(1, ['name' => 'Updated Name'], null)
            ->once()
            ->andReturn($author);

        $response = $this->controller->update(1, $request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDestroyDeletesAuthorSuccessfully()
    {
        $request = Mockery::mock(Request::class);
        $this->authorService->shouldReceive('delete')
            ->with(1, 1)
            ->once()
            ->andReturn(true);

        $request->shouldReceive('get')->with('reassignId')->andReturn(1);

        $response = $this->controller->destroy(1, $request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDestroyReturns404WhenAuthorNotFound()
    {
        $request = Mockery::mock(Request::class);

        $this->authorService->shouldReceive('delete')
            ->with(999, 1)
            ->once()
            ->andReturn(false);

        $request->shouldReceive('get')->with('reassignId')->andReturn(1);

        $response = $this->controller->destroy(999, $request, $this->siteSlug);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testMergeAuthorsSuccessfully()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('source_author_id')->andReturn(1);
        $request->shouldReceive('get')->with('target_author_id')->andReturn(2);

        $this->authorService->shouldReceive('mergeAuthors')
            ->with(1, 2)
            ->once()
            ->andReturn(true);

        $response = $this->controller->merge($request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMergeReturns400WhenParametersMissing()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('source_author_id')->andReturn(null);
        $request->shouldReceive('get')->with('target_author_id')->andReturn(2);

        $response = $this->controller->merge($request, $this->siteSlug);;

        $this->assertEquals(400, $response->getStatusCode());
    }

    private function createMockAuthor($id, $name)
    {
        $tag = Mockery::mock(Author::class);
        $tag->shouldReceive('toArray')->andReturn([
            'id' => $id,
            'name' => $name
        ]);
        return $tag;
    }
}