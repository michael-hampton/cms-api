<?php

namespace App\Tests\Unit\Controllers;

use App\Controllers\CategoryController;
use App\Framework\Http\Request;
use App\Framework\Validation\Validator;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Requests\CreateCategoryRequest;
use App\Requests\UpdateCategoryRequest;
use App\Search\PaginatedResult;
use App\Services\CategoryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class CategoryControllerTest extends FunctionalTestCase
{
    private $categoryRepository;
    private $validator;
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryRepository = Mockery::mock(CategoryRepository::class);
        $this->validator = Mockery::mock(Validator::class);
        $this->categoryService = Mockery::mock(CategoryService::class);
        $this->controller = new CategoryController($this->categoryRepository, $this->validator, $this->categoryService);;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }


    public function testIndexAppliesParentFilter()
    {
        $request = Mockery::mock(Request::class);

        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => 'php',
                    'search' => [
                        'filters' => ['parent' => 5],
                    ],
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $categories = collect([
            $this->createMockCategory(1, 'Tag 1'),
            $this->createMockCategory(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($categories->toArray(), $categories->count(), 1, 20);

        $this->categoryRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getFilter('parent') === 5;
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['pagination']['total']);
        $this->assertEquals('Tag 1', $data['items'][0]['name']);
    }

    public function testIndexAppliesSorting()
    {
        $request = Mockery::mock(Request::class);

        $request->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) {
                return match ($key) {
                    'q' => 'php',
                    'search' => null,
                    'sort_by' => 'usage',
                    'sort_order' => 'asc',
                    'page' => 1,
                    'per_page' => 20,
                    default => $default, // any other key => return its default
                };
            });

        $categories = collect([
            $this->createMockCategory(1, 'Tag 1'),
            $this->createMockCategory(2, 'Tag 2')
        ]);

        $result = new PaginatedResult($categories->toArray(), $categories->count(), 1, 20);

        $this->categoryRepository->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function($criteria) {
                return $criteria->getSortBy() === 'usage'
                    && $criteria->getSortOrder() === 'asc';
            }))
            ->andReturn($result);

        $response = $this->controller->index($request, $this->siteSlug);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['pagination']['total']);
        $this->assertEquals('Tag 1', $data['items'][0]['name']);
    }

    public function testTreeReturnsCategoryHierarchy()
    {
        $tree = [
            ['id' => 1, 'name' => 'Parent', 'children' => [
                ['id' => 2, 'name' => 'Child']
            ]]
        ];

        $this->categoryRepository->shouldReceive('getCategoryTree')
            ->once()
            ->andReturn($tree);

        $response = $this->controller->tree();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturnsCategoryByIdWhenNumeric()
    {
        $category = $this->createMockCategory(1, 'Test Category');

        $this->categoryRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($category);

        $response = $this->controller->show(1);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturnsCategoryBySlugWhenString()
    {
        $category = $this->createMockCategory(1, 'Test Category');

        $this->categoryRepository->shouldReceive('findBySlug')
            ->with('test-category')
            ->once()
            ->andReturn($category);

        $response = $this->controller->show('test-category');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturns404WhenCategoryNotFound()
    {
        $this->categoryRepository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $response = $this->controller->show(999);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testStoreCreatesCategory()
    {
        $request = Mockery::mock(CreateCategoryRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'name' => 'New Category',
            'slug' => 'new-category'
        ]);

        $category = $this->createMockCategory(1, 'New Category');

        $this->categoryRepository->shouldReceive('create')
            ->once()
            ->andReturn($category);

        $response = $this->controller->store($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testUpdateUpdatesCategory()
    {
        $request = Mockery::mock(UpdateCategoryRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'name' => 'Updated Category'
        ]);

        $category = $this->createMockCategory(1, 'Updated Category');

        $this->categoryRepository->shouldReceive('update')
            ->with(1, Mockery::any())
            ->once()
            ->andReturn($category);

        $response = $this->controller->update(1, $request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDestroyDeletesCategory()
    {
        // Create a real CategoryService for this test only
        $database = Mockery::mock(\App\Framework\Database\Database::class);
        $realCategoryService = new CategoryService($database, $this->categoryRepository);

        // Create a new controller instance with the real service
        $controller = new CategoryController(
            $this->categoryRepository,
            $this->validator,
            $realCategoryService
        );

        // Mock category with no pages using method chaining
        $category = Mockery::mock('App\Models\Category');
        $collection = Mockery::mock('App\Framework\Support\Collection');

        $this->categoryRepository->shouldReceive('getPagesByCategoryId')->with(1)->andReturn($collection);
        $collection->shouldReceive('count')->andReturn(0);

        // Mock repository to return the category
        $this->categoryRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($category);

        // Mock repository delete method (called when no pages to reassign)
        $this->categoryRepository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        // Mock Request
        $request = Mockery::mock(\App\Framework\Http\Request::class);
        $request->shouldReceive('input')
            ->with('reassignId')
            ->once()
            ->andReturn(null);

        // Call the controller (using local variable, not $this->controller)
        $response = $controller->destroy($request, 1);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Category deleted successfully', $data['data']['message']);
    }



    public function testPopularReturnsPopularCategories()
    {
        $categories = [
            $this->createMockCategory(1, 'Popular 1'),
            $this->createMockCategory(2, 'Popular 2')
        ];

        $this->categoryRepository->shouldReceive('getPopularCategories')
            ->with(20)
            ->once()
            ->andReturn($categories);

        $response = $this->controller->popular();

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function createMockCategory($id, $name)
    {
        $category = Mockery::mock(Category::class);
        $category->shouldReceive('toArray')->andReturn([
            'id' => $id,
            'name' => $name
        ]);
        return $category;
    }
}