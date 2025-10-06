<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Category;
use App\Models\PageCategory;

class CategoryControllerTest extends FunctionalTestCase
{
    public function testIndexReturnsCategories()
    {
        Category::create(['name' => 'Technology', 'slug' => 'technology', 'is_active' => true]);
        Category::create(['name' => 'Science', 'slug' => 'science', 'is_active' => true]);

        $response = $this->getForSite('/api/categories');

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

        $response = $this->getForSite('/api/categories?page=1&per_page=10');

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

    public function testDuplicateCategorySuccessfully(): void
    {
        $category = Category::create([
            'name' => 'Technology',
            'description' => 'Tech articles',
            'slug' => 'technology',
            'status' => 'active'
        ]);

        $response = $this->postJson("/api/categories/{$category->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Technology (Copy)', $data['data']['name']);
        $this->assertEquals('Tech articles', $data['data']['description']);
        $this->assertNotEquals($category->slug, $data['data']['slug']);
    }

    public function testDuplicateCategoryWithParent(): void
    {
        $parent = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'status' => 'active'
        ]);

        $child = Category::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'parent_id' => $parent->id,
            'status' => 'active'
        ]);

        $response = $this->postJson("/api/categories/{$child->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        // Verify parent relationship is maintained
        $this->assertEquals($parent->id, $data['data']['parent_id']);
        $this->assertEquals('Smartphones (Copy)', $data['data']['name']);
    }

    public function testDuplicateCategoryHandlesSlugConflict(): void
    {
        // Create original
        $category1 = Category::create([
            'name' => 'News',
            'slug' => 'news',
            'status' => 'active'
        ]);

        // Create first duplicate
        $response1 = $this->postJson("/api/categories/{$category1->id}/duplicate");
        $this->assertResponseOk($response1);
        $data1 = json_decode($response1->getContent(), true);

        // Create second duplicate - should handle slug conflict
        $response2 = $this->postJson("/api/categories/{$category1->id}/duplicate");
        $this->assertResponseOk($response2);
        $data2 = json_decode($response2->getContent(), true);

        // Verify slugs are unique
        $this->assertNotEquals($data1['data']['slug'], $data2['data']['slug']);
        $this->assertStringContainsString('news-copy', $data1['data']['slug']);
        $this->assertStringContainsString('news-copy', $data2['data']['slug']);
    }
}