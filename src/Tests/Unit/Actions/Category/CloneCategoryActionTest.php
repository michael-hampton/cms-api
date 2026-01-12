<?php

namespace App\Tests\Unit\Actions\Category;

use App\Actions\Category\CloneCategory;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Category;
use App\Repositories\Cms\CategoryRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class CloneCategoryActionTest extends FunctionalTestCase
{
    use HasSiteHistory;
    private CategoryRepository $categoryRepository;
    private Database $databaseMock;
    private CloneCategory $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->categoryRepository = Mockery::mock(CategoryRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->service = new CloneCategory($this->databaseMock, $this->categoryRepository);;

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

        $result = $this->service->handle(1);

        $this->assertEquals('Technology (Copy)', $result['category']->name);
    }

    public function testDuplicateCategoryWithSlugConflict(): void
    {
        $originalCategory = Mockery::mock(Category::class)->makePartial();
        $originalCategory->id = 1;
        $originalCategory->name = 'Technology';

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $this->categoryRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($originalCategory);

        $newCategory = Mockery::mock(Category::class)->makePartial();
        $newCategory->name = 'Technology (Copy)';
        $newCategory->id = 2;

        $this->setCloneHistoryExpectations($originalCategory, $newCategory, 1, 2);

        // First call returns existing category, second returns null
        $this->categoryRepository
            ->shouldReceive('findBySlug')
            ->with('technology-copy')
            ->once()
            ->andReturn($newCategory);

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
            ->andReturn($newCategory);

        $result = $this->service->handle(1);

        $this->assertEquals('Technology (Copy)', $result['category']->name);
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

        $newParent = Mockery::mock(Category::class)->makePartial();
        $newParent->id = 10;
        $newParent->name = 'Technology (Copy)';

        $this->setCloneHistoryExpectations($mockCategory, $newParent, 1, 10);

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

        $result = $this->service->handle(1);

        $this->assertEquals('Technology (Copy)', $result['category']->name);
    }

    public function testCloneCategoryReturnsDetailedResults()
    {
        $originalCategory = Mockery::mock(Category::class)->makePartial();
        $originalCategory->id = 1;
        $originalCategory->name = 'Technology';

        $newCategory = Mockery::mock(Category::class)->makePartial();
        $newCategory->id = 2;

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->categoryRepository->shouldReceive('find')->with(1)->andReturn($originalCategory);
        $this->categoryRepository->shouldReceive('findBySlug')->andReturn(null);
        $this->categoryRepository->shouldReceive('create')->andReturn($newCategory);
        $this->setCloneHistoryExpectations($originalCategory, $newCategory, 1, 2);

        $originalCategory->shouldReceive('children')->andReturn(collect([]));

        $result = $this->service->handle(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('original_category_id', $result);
        $this->assertContains('category_created', $result['results']['success']);
        $this->assertContains('clone_history', $result['results']['success']);
        $this->assertEquals(0, $result['results']['children_cloned']);
    }

    public function testCloneCategoryTracksChildrenCloning()
    {
        $parentCategory = Mockery::mock(Category::class)->makePartial();
        $parentCategory->id = 1;
        $parentCategory->name = 'Parent';

        $child1 = Mockery::mock(Category::class)->makePartial();
        $child1->id = 2;
        $child1->name = 'Child 1';

        $child2 = Mockery::mock(Category::class)->makePartial();
        $child2->id = 3;
        $child2->name = 'Child 2';

        $newParent = Mockery::mock(Category::class)->makePartial();
        $newParent->id = 10;

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->categoryRepository->shouldReceive('find')->with(1)->andReturn($parentCategory);
        $this->categoryRepository->shouldReceive('findBySlug')->andReturn(null);
        $this->categoryRepository->shouldReceive('create')->times(3)->andReturn(
            $newParent,
            new Category(['id' => 11]),
            new Category(['id' => 12])
        );
        $this->setCloneHistoryExpectations($parentCategory, $newParent, 1, 10);

        $parentCategory->shouldReceive('children')->once()->andReturn(collect([$child1, $child2]));
        $child1->shouldReceive('children')->once()->andReturn(collect([]));
        $child2->shouldReceive('children')->once()->andReturn(collect([]));

        $result = $this->service->handle(1);

        $this->assertEquals(2, $result['results']['children_cloned']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}