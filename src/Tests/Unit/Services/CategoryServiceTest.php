<?php

namespace App\Tests\Unit\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Category;
use App\Models\PageCategory;
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

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->delete($authorId, $reassignAuthorId);

        $this->assertTrue($result);
    }
}