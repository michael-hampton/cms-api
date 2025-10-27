<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Category;
use App\Models\Page;
use App\Models\PageCategory;
use App\Repositories\CategoryRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CategoryRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private CategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CategoryRepository();
    }

    public function testItCanFindCategoryBySlug(): void
    {
        // Arrange
        $category = $this->createCategory(['slug' => 'unique-category-slug', 'name' => 'Unique Category']);

        // Act
        $found = $this->repository->findBySlug('unique-category-slug');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($category->id, $found->id);
        $this->assertEquals('unique-category-slug', $found->slug);
    }

    public function test_it_returns_null_when_slug_not_found(): void
    {
        // Act
        $found = $this->repository->findBySlug('non-existent-slug');

        // Assert
        $this->assertNull($found);
    }

    public function test_it_filters_categories_by_site(): void
    {
        // Arrange
        $this->createCategory(['slug' => 'site-1-category', 'site_id' => $this->siteId]);
        $otherSite = $this->createSite();
        $this->createCategory(['slug' => 'site-2-category', 'site_id' => $otherSite->id]);

        // Act
        $found = $this->repository->findBySlug('site-1-category');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($this->siteId, $found->site_id);
    }

    public function test_get_active_returns_only_active_categories(): void
    {
        // Arrange
        $active1 = $this->createCategory(['name' => 'Active 1', 'is_active' => 1]);
        $active2 = $this->createCategory(['name' => 'Active 2', 'is_active' => 1]);
        $inactive = $this->createCategory(['name' => 'Inactive', 'is_active' => 0]);

        // Act
        $categories = $this->repository->getActive();

        // Assert
        $this->assertGreaterThanOrEqual(2, $categories->count());
        foreach ($categories as $category) {
            $this->assertEquals(1, $category->is_active);
        }
    }

    public function test_get_root_categories_returns_only_roots(): void
    {
        // Arrange
        $root1 = $this->createCategory(['name' => 'Root 1', 'parent_id' => null, 'is_active' => 1]);
        $root2 = $this->createCategory(['name' => 'Root 2', 'parent_id' => null, 'is_active' => 1]);
        $child = $this->createCategory(['name' => 'Child', 'parent_id' => $root1->id, 'is_active' => 1]);

        // Act
        $roots = $this->repository->getRootCategories();

        // Assert
        $this->assertGreaterThanOrEqual(2, count($roots));
        foreach ($roots as $category) {
            $this->assertNull($category->parent_id);
        }
    }

    public function test_get_child_categories_returns_only_children_of_parent(): void
    {
        // Arrange
        $parent = $this->createCategory(['name' => 'Parent']);
        $child1 = $this->createCategory(['name' => 'Child 1', 'parent_id' => $parent->id, 'is_active' => 1, 'sort_order' => 1]);
        $child2 = $this->createCategory(['name' => 'Child 2', 'parent_id' => $parent->id, 'is_active' => 1, 'sort_order' => 2]);
        $otherChild = $this->createCategory(['name' => 'Other Child', 'parent_id' => null, 'is_active' => 1]);

        // Act
        $children = $this->repository->getChildCategories($parent->id);

        // Assert
        $this->assertCount(2, $children);
        $this->assertCollectionContains($children, ['name' => 'Child 1']);
        $this->assertCollectionContains($children, ['name' => 'Child 2']);
        $this->assertCollectionDoesNotContain($children, ['name' => 'Other Child']);
    }

    public function test_get_child_categories_orders_by_sort_order_and_name(): void
    {
        // Arrange
        $parent = $this->createCategory(['name' => 'Parent']);
        $child1 = $this->createCategory(['name' => 'B Category', 'parent_id' => $parent->id, 'is_active' => 1, 'sort_order' => 2]);
        $child2 = $this->createCategory(['name' => 'A Category', 'parent_id' => $parent->id, 'is_active' => 1, 'sort_order' => 1]);

        // Act
        $children = $this->repository->getChildCategories($parent->id);

        // Assert
        $childrenArray = $children->toArray();
        $this->assertEquals('A Category', $childrenArray[0]['name']);
        $this->assertEquals('B Category', $childrenArray[1]['name']);
    }

    public function test_get_category_tree_builds_hierarchical_structure(): void
    {
        // Arrange
        $root = $this->createCategory(['name' => 'Root', 'parent_id' => null, 'is_active' => 1]);
        $child1 = $this->createCategory(['name' => 'Child 1', 'parent_id' => $root->id, 'is_active' => 1]);
        $grandchild = $this->createCategory(['name' => 'Grandchild', 'parent_id' => $child1->id, 'is_active' => 1]);

        // Act
        $tree = $this->repository->getCategoryTree();

        // Assert
        $this->assertIsArray($tree);
        $this->assertGreaterThan(0, count($tree));

        // Find our root in the tree
        $foundRoot = null;
        foreach ($tree as $item) {
            if ($item['id'] === $root->id) {
                $foundRoot = $item;
                break;
            }
        }

        $this->assertNotNull($foundRoot);
        $this->assertArrayHasKey('children', $foundRoot);
    }

    public function test_find_or_create_by_name_creates_new_category(): void
    {
        // Act
        $category = $this->repository->findOrCreateByName('New Category', $this->siteId);

        // Assert
        $this->assertNotNull($category);
        $this->assertEquals('New Category', $category->name);
        $this->assertEquals('new-category', $category->slug);
        $this->assertEquals(1, $category->is_active);
    }

    public function test_find_or_create_by_name_returns_existing_category(): void
    {
        // Arrange
        $existing = $this->createCategory(['name' => 'Existing Category', 'slug' => 'existing-category']);

        // Act
        $category = $this->repository->findOrCreateByName('Existing Category', $this->siteId);

        // Assert
        $this->assertEquals($existing->id, $category->id);

        // Verify no duplicate was created
        $count = Category::where('slug', 'existing-category')->where('site_id', $this->siteId)->count();
        $this->assertEquals(1, $count);
    }

    public function test_get_popular_categories_returns_categories_ordered_by_page_count(): void
    {
        // Arrange
        $category1 = $this->createCategory(['name' => 'Category 1', 'is_active' => 1]);
        $category2 = $this->createCategory(['name' => 'Category 2', 'is_active' => 1]);
        $category3 = $this->createCategory(['name' => 'Category 3', 'is_active' => 1]);

        // Create pages and associate with categories
        $page1 = $this->createPage();
        $page2 = $this->createPage();
        $page3 = $this->createPage();
        $page4 = $this->createPage();

        // Category 2 has most pages
        $this->attachCategoryToPage($page1, $category2);
        $this->attachCategoryToPage($page2, $category2);
        $this->attachCategoryToPage($page3, $category2);

        // Category 1 has one page
        $this->attachCategoryToPage($page4, $category1);

        // Act
        $popular = $this->repository->getPopularCategories(10);

        // Assert
        $this->assertGreaterThan(0, count($popular));
        // Category 2 should be first (most pages)
        $this->assertEquals($category2->id, $popular->first()->id);
    }

    public function test_get_popular_categories_respects_limit(): void
    {
        // Arrange
        for ($i = 1; $i <= 15; $i++) {
            $this->createCategory(['name' => "Category $i", 'is_active' => 1]);
        }

        // Act
        $popular = $this->repository->getPopularCategories(5);

        // Assert
        $this->assertCount(5, $popular);
    }

    public function test_get_alternatives_excludes_specified_category(): void
    {
        // Arrange
        $category1 = $this->createCategory(['name' => 'Category 1']);
        $category2 = $this->createCategory(['name' => 'Category 2']);
        $category3 = $this->createCategory(['name' => 'Category 3']);

        // Act
        $alternatives = $this->repository->getAlternatives($category2->id);

        // Assert
        $this->assertGreaterThanOrEqual(2, $alternatives->count());
        $this->assertCollectionDoesNotContain($alternatives, ['id' => $category2->id]);
    }

    public function test_get_pages_by_category_id_returns_correct_pages(): void
    {
        // Arrange
        $category = $this->createCategory();
        $page1 = $this->createPage(['title' => 'Page 1']);
        $page2 = $this->createPage(['title' => 'Page 2']);
        $page3 = $this->createPage(['title' => 'Page 3']);

        $this->attachCategoryToPage($page1, $category);
        $this->attachCategoryToPage($page2, $category);

        // Act
        $pages = $this->repository->getPagesByCategoryId($category->id);

        // Assert
        $this->assertCount(2, $pages);
    }

    public function test_get_pages_by_category_id_respects_limit(): void
    {
        // Arrange
        $category = $this->createCategory();
        $pages = $this->createPages(5);

        foreach ($pages as $page) {
            $this->attachCategoryToPage($page, $category);
        }

        // Act
        $result = $this->repository->getPagesByCategoryId($category->id, 2);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_search_returns_paginated_results(): void
    {
        // Arrange
        $this->createCategory(['name' => 'Category 1']);
        $this->createCategory(['name' => 'Category 2']);
        $this->createCategory(['name' => 'Category 3']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(2);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
        $this->assertGreaterThan(0, count($result->getData()));
    }
}