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

    public function testDuplicateCategorySuccessfully(): void
    {
        $originalCategory = new Category([
            'id' => 1,
            'name' => 'Technology',
            'description' => 'Tech articles',
            'parent_id' => null,
            'slug' => 'technology',
            'seo_title' => 'Tech SEO Title',
            'seo_description' => 'Tech SEO Description',
            'no_index' => false,
            'canonical_url' => 'https://example.com/tech'
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
                    && $data['status'] === 'inactive'
                    && $data['seo_title'] === 'Tech SEO Title'
                    && $data['seo_description'] === 'Tech SEO Description'
                    && $data['no_index'] === false
                    && $data['canonical_url'] === null;
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

    public function testDuplicateCategoryWithChildren(): void
    {
        $mockCategory = Mockery::mock(Category::class)->makePartial();
        $mockCategory->id = 1;

        $child1 = Mockery::mock(Category::class)->makePartial();
        $child1->id = 2;
        $child1->parent_id = 1;
        $child1->name = 'Test1';

        $child2 = Mockery::mock(Category::class)->makePartial();
        $child2->id = 3;
        $child2->parent_id = 1;
        $child2->name = 'Test2';

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->categoryRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($mockCategory);

        $this->categoryRepository
            ->shouldReceive('findBySlug')
            ->andReturn(null);

        $newParent = new Category([
            'id' => 10,
            'name' => 'Technology (Copy)',
            'slug' => 'technology-copy'
        ]);

        $this->categoryRepository
            ->shouldReceive('create')
            ->times(3) // parent + 2 children
            ->andReturn($newParent, new Category(['id' => 11]), new Category(['id' => 12]));


        // Mock children() to return collection
        $childrenMock = Mockery::mock(Collection::class);
        $childrenMock->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator([$child1, $child2]));

        $mockCategory->shouldReceive('children')
            ->once()
            ->andReturn($childrenMock);

        // Mock for recursive children (no grandchildren)
        $emptyChildren = Mockery::mock(Collection::class);
        $emptyChildren->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator([]));

        $child1->shouldReceive('children')->once()->andReturn($emptyChildren);
        $child2->shouldReceive('children')->once()->andReturn($emptyChildren);

        $result = $this->service->duplicateCategory(1);

        $this->assertTrue($result);
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

        $result = $this->service->bulkDelete([1, 2]);

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

        $result = $this->service->bulkDelete([1]);

        $this->assertCount(0, $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('subcategories', $result['failed'][0]['reason']);
    }
}