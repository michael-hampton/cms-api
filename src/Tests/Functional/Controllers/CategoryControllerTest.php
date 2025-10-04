<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Category;
use App\Models\PageCategory;

class CategoryControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testIndexReturnsCategories()
    {
        Category::create(['name' => 'Technology', 'slug' => 'technology', 'is_active' => true]);
        Category::create(['name' => 'Science', 'slug' => 'science', 'is_active' => true]);

        $response = $this->get('/api/categories');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function testIndexWithPagination()
    {
        for ($i = 1; $i <= 15; $i++) {
            Category::create(['name' => "Category $i", 'slug' => "category-$i", 'is_active' => true]);
        }

        $response = $this->get('/api/categories?page=1&per_page=10');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(10, $data['items']);
        $this->assertEquals(15, $data['pagination']['total']);
    }

//    public function testTreeReturnsHierarchicalStructure()
//    {
//        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent', 'is_active' => true]);
//        Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id, 'is_active' => true]);
//
//        $response = $this->get('/api/categories/tree');
//
//        $this->assertEquals(200, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//        $this->assertArrayHasKey('tree', $data);
//        $this->assertCount(1, $data['tree']);
//        $this->assertArrayHasKey('children', $data['tree'][0]);
//    }

    public function testShowReturnsCategoryById()
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology', 'is_active' => true]);

        $response = $this->get("/api/categories/{$category->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Technology', $data['data']['category']['name']);
    }

    public function testShowReturnsCategoryBySlug()
    {
        Category::create(['name' => 'Technology', 'slug' => 'technology', 'is_active' => true]);

        $response = $this->get('/api/categories/technology');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Technology', $data['data']['category']['name']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->get('/api/categories/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testStoreCreatesCategory()
    {
        $categoryData = [
            'name' => 'Technology',
            'description' => 'Tech articles',
            'color' => '#FF5733',
            'is_active' => true
        ];

        $response = $this->post('/api/categories', $categoryData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Technology', $data['data']['category']['name']);
        $this->assertEquals('technology', $data['data']['category']['slug']);
    }

    public function testStoreAutoGeneratesSlug()
    {
        $response = $this->post('/api/categories', ['name' => 'My New Category']);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('my-new-category', $data['data']['category']['slug']);
    }

    public function testStoreValidatesUniqueSlug()
    {
        Category::create(['name' => 'Technology', 'slug' => 'technology', 'is_active' => true]);

        $response = $this->post('/api/categories', [
            'name' => 'New Tech',
            'slug' => 'technology'
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreValidatesParentExists()
    {
        $response = $this->post('/api/categories', [
            'name' => 'Child Category',
            'parent_id' => 999
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateModifiesCategory()
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology', 'is_active' => true]);

        $response = $this->put("/api/categories/{$category->id}", [
            'name' => 'Updated Technology',
            'description' => 'New description',
            'color' => '#000000'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Technology', $data['data']['category']['name']);
    }

    public function testUpdateReturns404ForNonexistent()
    {
        $response = $this->put('/api/categories/999', ['name' => 'Test']);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDestroyDeletesCategory()
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology', 'is_active' => true]);

        $response = $this->delete("/api/categories/{$category->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Category::find($category->id));
    }

    public function testDestroyReturns404ForNonexistent()
    {
        $response = $this->delete('/api/categories/999');

        $this->assertEquals(404, $response->getStatusCode());
    }


    public function testCheckDeleteCategoryReturnsCanDeleteWhenNoPagesExist()
    {
        // Arrange: create an author with no pages
        $category = Category::create([
            'name' => 'Lonely Author',
            'slug' => 'lonely-author',
        ]);

        // Act
        $response = $this->get("/api/categories/{$category->id}/check-delete");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('can_delete', $data['data']);
        $this->assertTrue($data['data']['can_delete']);
        $this->assertEquals(0, $data['data']['pages_count']);
        $this->assertFalse($data['data']['requires_reassignment']);
    }

    public function testCheckDeleteCategoryReturnsRequiresReassignmentWhenPagesExist()
    {
        // Arrange: create an author that has pages
        $category = Category::create([
            'name' => 'Author With Pages',
            'slug' => 'author-with-pages',
        ]);

        // Create one or more pages for this author
        $page = \App\Models\Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
        ]);

        PageCategory::create([
            'page_id' => $page->id,
            'category_id' => $category->id,
        ]);

        // Act
        $response = $this->get("/api/categories/{$category->id}/check-delete");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['requires_reassignment']);
        $this->assertGreaterThan(0, $data['data']['pages_count']);
        $this->assertArrayHasKey('alternatives', $data['data']);
        $this->assertIsArray($data['data']['alternatives']);
    }

    public function testCheckDeleteCategoryReturns404WhenAuthorNotFound()
    {
        // Act
        $response = $this->get('/api/categories/9999/check-delete');

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Category not found', $data['data']['message']);
    }
}