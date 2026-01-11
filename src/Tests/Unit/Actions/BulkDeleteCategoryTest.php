<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkDeleteCategory;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Category;
use App\Repositories\Cms\CategoryRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkDeleteCategoryTest extends FunctionalTestCase
{
    use HasSiteHistory;
    private CategoryRepository $categoryRepository;
    private Database $databaseMock;
    private BulkDeleteCategory $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->categoryRepository = Mockery::mock(CategoryRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->service = new BulkDeleteCategory($this->databaseMock, $this->categoryRepository);;

    }

    public function testBulkDeleteSuccessfully(): void
    {
        $category1 = Mockery::mock(Category::class)->makePartial();
        $category2 = Mockery::mock(Category::class)->makePartial();

        $emptyCollection = Mockery::mock(Collection::class);
        $emptyCollection->shouldReceive('count')->andReturn(0);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->categoryRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($category1);

        $this->categoryRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($category2);

        $category1->shouldReceive('children')->once()->andReturn($emptyCollection);
        $category2->shouldReceive('children')->once()->andReturn($emptyCollection);

        $this->categoryRepository->shouldReceive('getPagesByCategoryId')
            ->twice()
            ->andReturn(collect([]));

        $this->categoryRepository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $this->categoryRepository->shouldReceive('delete')
            ->with(2)
            ->once()
            ->andReturn(true);

        $result = $this->service->handle([1, 2]);

        $this->assertCount(2, $result['deleted']);
        $this->assertCount(0, $result['failed']);
    }

    public function testBulkDeleteFailsWhenChildrenExist(): void
    {
        $category1 = Mockery::mock(Category::class)->makePartial();

        $childrenCollection = Mockery::mock(Collection::class);
        $childrenCollection->shouldReceive('count')->once()->andReturn(2);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->categoryRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($category1);

        $category1->shouldReceive('children')->once()->andReturn($childrenCollection);

        $result = $this->service->handle([1]);

        $this->assertCount(0, $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('subcategories', $result['failed'][0]['reason']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}