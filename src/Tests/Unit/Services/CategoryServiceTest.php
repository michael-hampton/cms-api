<?php

namespace App\Tests\Unit\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Category;
use App\Models\PageCategory;
use App\Repositories\CategoryRepository;
use App\Services\CategoryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class CategoryServiceTest extends FunctionalTestCase
{
    private CategoryRepository $categoryRepository;
    private Database $databaseMock;
    private CategoryService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->categoryRepository = Mockery::mock(CategoryRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->service = new CategoryService($this->databaseMock, $this->categoryRepository);;

    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testItCanDeleteCategoryWithoutPages()
    {
        $categoryId = 1;
        $category = Mockery::mock(Category::class)->makePartial();

        $collection = Mockery::mock(Collection::class);
        $this->categoryRepository->shouldReceive('getPagesByCategoryId')
            ->with($categoryId)->once()
            ->andReturn($collection);
        $collection->shouldReceive('count')->once()->andReturn(0);

        $this->categoryRepository->shouldReceive('delete')
            ->with($categoryId)
            ->andReturn(true)
            ->once();

        $this->categoryRepository->shouldReceive('find')
            ->with($categoryId)
            ->once()
            ->andReturn($category);

        $result = $this->service->delete($categoryId);

        $this->assertTrue($result);
    }

    public function testItThrowsExceptionWhenDeletingCategoryWithPagesWithoutReassignment()
    {
        $categoryId = 1;
        $category = Mockery::mock(Category::class)->makePartial();

        $collection = Mockery::mock(Collection::class);
        $this->categoryRepository->shouldReceive('getPagesByCategoryId')
            ->with($categoryId)
            ->once()
            ->andReturn($collection);
        $collection->shouldReceive('count')->once()->andReturn(2);

        $this->categoryRepository->shouldReceive('find')
            ->with($categoryId)
            ->once()
            ->andReturn($category);

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($categoryId);
    }

    public function testItCanDeleteCategoryAndReassignPages()
    {
        $authorId = 1;
        $reassignAuthorId = 2;
        $author = Mockery::mock(Category::class);
        $reassignAuthor = Mockery::mock(Category::class);

        // Mock a page that will be reassigned
        $page = Mockery::mock(PageCategory::class)->makePartial();
        $page->shouldReceive('save')->once();

        $this->categoryRepository->shouldReceive('find')
            ->with($authorId)
            ->once()
            ->andReturn($author);

        $this->categoryRepository->shouldReceive('find')
            ->with($reassignAuthorId)
            ->once()
            ->andReturn($reassignAuthor);

        // Called twice: once for count check, once inside transaction
        $this->categoryRepository->shouldReceive('getPagesByCategoryId')
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

    public function testDuplicateCategorySuccessfully(): void
    {
        $originalCategory = new Category([
            'id' => 1,
            'name' => 'Technology',
            'description' => 'Tech articles',
            'parent_id' => null,
            'slug' => 'technology'
        ]);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->categoryRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalCategory);

        $this->categoryRepository
            ->shouldReceive('findBySlug')
            ->with('technology-copy')
            ->once()
            ->andReturn(null);

        $newCategory = new Category([
            'id' => 2,
            'name' => 'Technology (Copy)',
            'slug' => 'technology-copy'
        ]);

        $this->categoryRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['name'] === 'Technology (Copy)'
                    && $data['slug'] === 'technology-copy'
                    && $data['status'] === 'inactive';
            }))
            ->andReturn($newCategory);

        $result = $this->service->duplicateCategory(1);

        $this->assertTrue($result);
    }

    public function testDuplicateCategoryWithSlugConflict(): void
    {
        $originalCategory = new Category([
            'id' => 1,
            'name' => 'Technology',
            'slug' => 'technology'
        ]);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->categoryRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalCategory);

        // First call returns existing category, second returns null
        $this->categoryRepository
            ->shouldReceive('findBySlug')
            ->with('technology-copy')
            ->once()
            ->andReturn(new Category(['id' => 99]));

        $this->categoryRepository
            ->shouldReceive('findBySlug')
            ->with('technology-copy-1')
            ->once()
            ->andReturn(null);

        $this->categoryRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['slug'] === 'technology-copy-1';
            }))
            ->andReturn(new Category(['id' => 2]));

        $result = $this->service->duplicateCategory(1);

        $this->assertTrue($result);
    }
}