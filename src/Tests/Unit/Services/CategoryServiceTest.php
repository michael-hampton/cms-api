<?php

namespace App\Tests\Unit\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Services\CategoryService;
use Mockery;
use PHPUnit\Framework\TestCase;

class CategoryServiceTest extends TestCase
{
    private CategoryRepository $categoryRepository;
    private Database $database;
    private CategoryService $service;

    public function setUp(): void
    {
        $this->categoryRepository = Mockery::mock(CategoryRepository::class);
        $this->database = Mockery::mock(Database::class);
        $this->service = new CategoryService($this->database, $this->categoryRepository);;
        parent::setUp();
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

        $category->shouldReceive('pages->count')
            ->once()
            ->andReturn(0);

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

        $category->shouldReceive('pages->count')
            ->once()
            ->andReturn(3);

        $this->categoryRepository->shouldReceive('find')
            ->with($categoryId)
            ->once()
            ->andReturn($category);

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($categoryId);
    }

    public function testItCanDeleteCategoryAndReassignPages()
    {
        $categoryId = 1;
        $reassignCategoryId = 2;
        $category = Mockery::mock(Category::class)->makePartial();
        $reassignCategory = Mockery::mock(Category::class)->makePartial();
        $pages = Mockery::mock();

        $category->shouldReceive('pages->count')
            ->once()
            ->andReturn(3);

        $category->shouldReceive('pages->update')
            ->with(['category_id' => $reassignCategoryId])
            ->once();

        $category->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->categoryRepository->shouldReceive('find')
            ->with($categoryId)
            ->once()
            ->andReturn($category);

        $this->categoryRepository->shouldReceive('find')
            ->with($reassignCategoryId)
            ->once()
            ->andReturn($reassignCategory);

        $result = $this->service->delete($categoryId, $reassignCategoryId);

        $this->assertTrue($result);
    }
}