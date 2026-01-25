<?php

namespace App\Tests\Unit\Services\Cms;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Category;
use App\Models\PageCategory;
use App\Repositories\Cms\CategoryRepository;
use App\Services\Cms\CategoryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class CategoryServiceTest extends FunctionalTestCase
{
    use HasSiteHistory;
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

        $author->shouldReceive('children')
            ->once()
            ->andReturn(collect([]));

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

    public function testCannotDeleteCategoryWithChildren(): void
    {
        $parent = Mockery::mock(Category::class);
        $childrenCollection = Mockery::mock(Collection::class);

        $this->categoryRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($parent);

        $parent->shouldReceive('children')
            ->once()
            ->andReturn($childrenCollection);

        $childrenCollection->shouldReceive('count')
            ->once()
            ->andReturn(2);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete category with 2 subcategories');

        $this->service->delete(1);
    }

    public function testCheckDeletableReturnsChildrenCount(): void
    {
        $category = Mockery::mock(Category::class);
        $pagesCollection = Mockery::mock(Collection::class);
        $childrenCollection = Mockery::mock(Collection::class);

        $this->categoryRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($category);

        $category->shouldReceive('pages')
            ->once()
            ->andReturn($pagesCollection);

        $category->shouldReceive('children')
            ->once()
            ->andReturn($childrenCollection);

        $pagesCollection->shouldReceive('count')
            ->once()
            ->andReturn(0);

        $childrenCollection->shouldReceive('count')
            ->once()
            ->andReturn(3);

        $result = $this->service->checkDeletable(1);

        $this->assertFalse($result['can_delete']);
        $this->assertEquals(3, $result['children_count']);
        $this->assertTrue($result['has_children']);
    }

    public function testGetAlternativeCategories()
    {
        $alternatives = collect([
            Mockery::mock(Category::class),
            Mockery::mock(Category::class)
        ]);

        $this->categoryRepository->shouldReceive('getAlternatives')
            ->with(1)
            ->once()
            ->andReturn($alternatives);

        $result = $this->service->getAlternativeCategories(1);

        $this->assertCount(2, $result);
    }
}