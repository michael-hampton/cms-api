<?php

namespace App\Tests\Unit\Controllers;

use App\Controllers\PageController;
use App\Framework\Database\Database;
use App\Framework\Http\Request;
use App\Framework\Support\Collection;
use App\Models\Page;
use App\Parsers\BlockRegistry;
use App\Repositories\PageRepository;
use App\Search\PaginatedResult;
use App\Services\PageService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class PageControllerTest extends FunctionalTestCase
{
    private $pageService;
    private $blockRegistry;
    private $controller;
    private $pageRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageService = Mockery::mock(PageService::class);
        $this->blockRegistry = Mockery::mock(BlockRegistry::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->controller = new PageController($this->pageService, $this->blockRegistry, $this->pageRepository);;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testIndexAppliesSearchFiltersForPages()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'status' => 'published',
                    'page_type' => 'article',
                    'author' => 1,
                    'featured' => true,
                    'category' => 5,
                    'tag' => 10,
                    'parent' => null,
                    'q' => 'test query',
                    'search' => null,
                    'sort_by' => 'title',
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $pages = collect([
            $this->createMockPage(1, 'Tag 1'),
            $this->createMockPage(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($pages->toArray(), $pages->count(), 1, 20);

        $this->pageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getFilter('status') === 'published'
                    && $criteria->getFilter('page_type') === 'article'
                    && $criteria->getFilter('author') === 1
                    && $criteria->getSortBy() === 'title'
                    && $criteria->getSearchQuery() === 'test query';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['pagination']['total']);
        $this->assertEquals('Tag 1', $data['items'][0]['title']);
    }

    public function testIndexHandlesPagination()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => 'php',
                    'search' => null,
                    'sort_order' => 'asc',
                    'page' => 2,
                    'per_page' => 50,
                    default => $default, // any other key => return its default
                };
            });

        $pages = collect([
            $this->createMockPage(1, 'Tag 1'),
            $this->createMockPage(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($pages->toArray(), $pages->count(), 2, 50);

        $this->pageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getPage() === 2
                    && $criteria->getPerPage() === 50;
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);;

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['pagination']['total']);
        $this->assertEquals('Tag 1', $data['items'][0]['title']);
    }

    public function testIndexAppliesMultipleStatusFilters()
    {
        $request = $this->createMockRequest([
            'status' => 'published,draft'
        ]);

        $result = new PaginatedResult([], 0, 1, 20);

        $this->pageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                $status = $criteria->getFilter('status');
                return $status === 'published,draft';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexAppliesMultipleAuthorFilters()
    {
        $request = $this->createMockRequest([
            'author' => '1,2,3'
        ]);

        $result = new PaginatedResult([], 0, 1, 20);

        $this->pageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function ($criteria) {
                // Split the string into an array for the test
                $authors = explode(',', $criteria->getFilter('author'));
                return is_array($authors) && count($authors) === 3
                    && $authors === ['1','2','3'];
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);;

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexAppliesMultipleCategoryFilters()
    {
        $request = $this->createMockRequest([
            'category' => [5, 10, 15] // Already an array
        ]);

        $result = new PaginatedResult([], 0, 1, 20);

        $this->pageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function ($criteria) {
                $categories = $criteria->getFilter('category');

                // Ensure it is always treated as array
                if (is_string($categories)) {
                    $categories = explode(',', $categories);
                }

                return is_array($categories) && count($categories) === 3
                    && $categories === [5, 10, 15];
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexAppliesTemplateFilter()
    {
        $request = $this->createMockRequest([
            'template' => 'article'
        ]);

        $result = new PaginatedResult([], 0, 1, 20);

        $this->pageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getFilter('template') === 'article';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexAppliesFeaturedFilter()
    {
        $request = $this->createMockRequest([
            'featured' => 'true'
        ]);

        $result = new PaginatedResult([], 0, 1, 20);

        $this->pageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getFilter('featured') === 'true';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexAppliesSorting()
    {
        $request = $this->createMockRequest([
            'sort_by' => 'title',
            'sort_order' => 'asc'
        ]);

        $result = new PaginatedResult([], 0, 1, 20);

        $this->pageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getSortBy() === 'title' && $criteria->getSortOrder() === 'asc';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexAppliesSearchQuery()
    {
        $request = $this->createMockRequest([
            'q' => 'test search',
        ]);

        $result = new PaginatedResult([], 0, 1, 20);

        $this->pageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getSearchQuery() === 'test search';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIndexHandlesPaginationCorrectly()
    {
        $request = $this->createMockRequest([
            'page' => 3,
            'per_page' => 50
        ]);

        $result = new PaginatedResult([], 150, 3, 50);

        $this->pageRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getPage() === 3 && $criteria->getPerPage() === 50;
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(3, $content['pagination']['current_page']);
        $this->assertEquals(50, $content['pagination']['per_page']);
        $this->assertEquals(150, $content['pagination']['total']);
        $this->assertEquals(3, $content['pagination']['total_pages']);
        $this->assertFalse($content['pagination']['has_more']);
    }


    public function testStoreCreatesNewPageWhenNoId()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('all')->andReturn([
            'forms' => ['main' => ['title' => 'New Page']],
            'blocks' => []
        ]);

        $request->shouldReceive('get')->with('site_id')->andReturn($this->siteId);

        $page = $this->createMockPage(1, 'New Page');

        $this->pageService->shouldReceive('createPageWithAllData')
            ->once()
            ->andReturn($page);

        $response = $this->controller->store($request, $this->siteSlug);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testStoreUpdatesExistingPageWhenIdPresent()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('all')->andReturn([
            'site_id' => $this->siteId,
            'id' => 1,
            'forms' => ['main' => ['title' => 'Updated Page']],
            'blocks' => []
        ]);

        $request->shouldReceive('get')->with('site_id')->andReturn($this->siteId);

        $page = $this->createMockPage(1, 'Updated Page');

        $this->pageService->shouldReceive('updatePageWithAllData')
            ->once()
            ->andReturn($page);

        $response = $this->controller->store($request, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturnsPageByIdWhenNumeric()
    {
        $page = $this->createMockPage(1, 'Test Page');

        $this->pageService->shouldReceive('getCompletePageData')
            ->with(1)
            ->once()
            ->andReturn($page);

        $response = $this->controller->show(1);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturnsPageBySlugWhenString()
    {
        $page = $this->createMockPage(1, 'Test Page');

        $this->pageService->shouldReceive('findPageBySlug')
            ->with('test-page')
            ->once()
            ->andReturn($page);

        $response = $this->controller->show('test-page');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturns404WhenPageNotFound()
    {
        $this->pageService->shouldReceive('getCompletePageData')
            ->with(999)
            ->andReturn(null);

        $response = $this->controller->show(999);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDestroyDeletesPageSuccessfully()
    {
        $this->pageService->shouldReceive('deletePage')
            ->with(1)
            ->once()
            ->andReturn(true);

        $response = $this->controller->destroy(1, $this->siteSlug);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDuplicateCreatesNewPageCopy()
    {
        $newPage = $this->createMockPage(2, 'Page Copy');
        $newPage->shouldReceive('toArrayWithRelations')->andReturn([
            'id' => 2,
            'title' => 'Page Copy'
        ]);

        $this->pageService->shouldReceive('duplicatePage')
            ->with(1)
            ->once()
            ->andReturn($newPage);

        $response = $this->controller->duplicate(1, $this->siteSlug);

        $this->assertEquals(201, $response->getStatusCode());
    }

    private function createMockPage($id, $title)
    {
        $page = Mockery::mock(Page::class, [[], $this->createMock(Database::class)])->makePartial();
        $page->id = $id;
        $page->title = $title;
        $page->shouldReceive('toArray')->andReturn([
            'id' => $id,
            'title' => $title
        ]);
        $page->blocks = new Collection([]);
        $page->categories = collect([]);
        $page->tags = collect([]);
        $page->custom_fields = collect([]);
        $page->metadata = null;
        $page->social = null;
        $page->seo = null;
        $page->settings = null;
        $page->site_id = $this->siteId;

        return $page;
    }

    private function createMockRequest(array $params = []): Request
    {
        $request = Mockery::mock(Request::class);

        $defaultParams = [
            'status' => null,
            'visibility' => null,
            'template' => null,
            'author' => null,
            'featured' => null,
            'category' => null,
            'tag' => null,
            'parent' => null,
            'mime_type' => null,
            'category_id' => null,
            'page_type' => null,
            'sort_by' => null,
            'sort_order' => 'asc',
            'page' => 1,
            'per_page' => 20,
            'q' => null,
            'search' => null
        ];

        $mergedParams = array_merge($defaultParams, $params);

        foreach ($mergedParams as $key => $value) {
            if ($key === 'sort_order') {
                $request->shouldReceive('get')->with($key, 'asc')->andReturn($value);
            } elseif ($key === 'page') {
                $request->shouldReceive('get')->with($key, 1)->andReturn($value);
            } elseif ($key === 'per_page') {
                $request->shouldReceive('get')->with($key, 1000)->andReturn($value);
            } else {
                $request->shouldReceive('get')->with($key)->andReturn($value);
            }
        }

        return $request;
    }
}