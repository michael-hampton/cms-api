<?php

namespace App\Tests\Unit\Controllers;

use App\Controllers\TagController;
use App\Framework\Http\Request;
use App\Framework\Support\Collection;
use App\Framework\Validation\Validator;
use App\Models\Tag;
use App\Repositories\TagRepository;
use App\Search\PaginatedResult;
use App\Services\TagService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class TagControllerTest extends FunctionalTestCase
{
    private $tagRepository;
    private $validator;
    private $controller;
    private $tagService;
    protected function setUp(): void
    {
        parent::setUp();

        $this->tagRepository = Mockery::mock(TagRepository::class);
        $this->validator = Mockery::mock(Validator::class);
        $this->tagService = Mockery::mock(TagService::class);
        $this->controller = new TagController($this->tagRepository, $this->validator, $this->tagService);;;
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
                    'q' => 'php',
                    'search' => null,
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $tags = collect([
            $this->createMockTag(1, 'Tag 1'),
            $this->createMockTag(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($tags->toArray(), $tags->count(), 1, 20);

        $this->tagRepository->shouldReceive('search')
            ->once()
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['pagination']['total']);
        $this->assertEquals('Tag 1', $data['items'][0]['name']);
    }

    public function testIndexAppliesSortByUsage()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => '',
                    'search' => null,
                    'sort_by' => 'usage',
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $tags = collect([
            $this->createMockTag(1, 'Tag 1'),
            $this->createMockTag(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($tags->toArray(), 2, 1, 20);

        $this->tagRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getSortBy() === 'usage'
                    && $criteria->getSortOrder() === 'asc'; // match request
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Tag 1', $data['items'][0]['name']);
        $this->assertEquals(2, $data['pagination']['total']);
    }

    public function testIndexAppliesSortByName()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => '',
                    'search' => null,
                    'sort_by' => 'name',
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $tags = collect([
            $this->createMockTag(1, 'Tag 1'),
            $this->createMockTag(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($tags->toArray(), $tags->count(), 1, 20);

        $this->tagRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getSortBy() === 'name'
                    && $criteria->getSortOrder() === 'asc';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);;

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Tag 1', $data['items'][0]['name']);
        $this->assertEquals(2, $data['pagination']['total']);
    }

    public function testIndexHandlesNoFilters()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => '',
                    'search' => ['site' => $this->siteSlug],
                    'sort_by' => null,
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $result = new PaginatedResult([], 0, 1, 20);

        $this->tagRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return empty($criteria->getFilters());
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSearchFiltersTagsByQuery()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('q', '')->andReturn('test');
        $request->shouldReceive('get')->with('limit', 10)->andReturn(10);

        $tags = [$this->createMockTag(1, 'Test Tag')];

        $this->tagRepository->shouldReceive('searchTags')
            ->with('test', 10)
            ->once()
            ->andReturn(new Collection($tags));

        $response = $this->controller->search($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPopularReturnsPopularTags()
    {
        $tags = [$this->createMockTag(1, 'Popular Tag')];

        $this->tagRepository->shouldReceive('getPopularTags')
            ->with(30)
            ->once()
            ->andReturn(new Collection($tags));

        $response = $this->controller->popular();

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function createMockTag($id, $name)
    {
        $tag = Mockery::mock(Tag::class);
        $tag->shouldReceive('toArray')->andReturn([
            'id' => $id,
            'name' => $name
        ]);
        return $tag;
    }

    public function testFeaturedReturnsFeaturedTags()
    {
        $tags = [$this->createMockTag(1, 'Featured Tag')];

        $this->tagRepository->shouldReceive('getFeaturedTags')
            ->once()
            ->andReturn(new Collection($tags));

        $response = $this->controller->featured();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCloudReturnsTagCloud()
    {
        $tags = [$this->createMockTag(1, 'Cloud Tag')];

        $this->tagRepository->shouldReceive('getTagCloud')
            ->with(100)
            ->once()
            ->andReturn(new Collection($tags));

        $response = $this->controller->cloud();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCleanupRemovesUnusedTags()
    {
        $this->tagRepository->shouldReceive('cleanupUnusedTags')
            ->once()
            ->andReturn(5);

        $response = $this->controller->cleanup();

        $this->assertEquals(200, $response->getStatusCode());
    }
}