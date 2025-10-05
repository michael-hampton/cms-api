<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Page;
use App\Models\PageTag;
use App\Models\Tag;

class TagControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testIndexReturnsTags()
    {
        Tag::create(['name' => 'PHP', 'slug' => 'php']);
        Tag::create(['name' => 'JavaScript', 'slug' => 'javascript']);
        $response = $this->get('/api/tags');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function testShowReturnsTagById()
    {
        $tag = Tag::create(['name' => 'PHP', 'slug' => 'php']);
        $response = $this->get("/api/tags/{$tag->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('PHP', $data['data']['tag']['name']);
    }

    public function testShowReturnsTagBySlug()
    {
        Tag::create(['name' => 'PHP', 'slug' => 'php']);
        $response = $this->get('/api/tags/php');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('PHP', $data['data']['tag']['name']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->get('/api/tags/999');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testStoreCreatesTag()
    {
        $tagData = ['name' => 'PHP', 'description' => 'PHP programming language', 'color' => '#777BB4', 'is_featured' => true];
        $response = $this->post('/api/tags', $tagData);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('PHP', $data['data']['tag']['name']);
        $this->assertEquals('php', $data['data']['tag']['slug']);
    }

    public function testStoreAutoGeneratesSlug()
    {
        $response = $this->post('/api/tags', ['name' => 'My New Tag']);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('my-new-tag', $data['data']['tag']['slug']);
    }

    public function testStoreValidatesUniqueSlug()
    {
        Tag::create(['name' => 'PHP', 'slug' => 'php']);
        $response = $this->post('/api/tags', ['name' => 'New PHP', 'slug' => 'php']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateModifiesTag()
    {
        $tag = Tag::create(['name' => 'PHP', 'slug' => 'php']);
        $response = $this->put("/api/tags/{$tag->id}", ['name' => 'PHP 8', 'description' => 'Updated description', 'is_featured' => true]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('PHP 8', $data['data']['tag']['name']);
    }

    public function testUpdateReturns404ForNonexistent()
    {
        $response = $this->put('/api/tags/999', ['name' => 'Test']);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDestroyDeletesTag()
    {
        $tag = Tag::create(['name' => 'PHP', 'slug' => 'php']);
        $response = $this->delete("/api/tags/{$tag->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Tag::find($tag->id));
    }

    public function testPopularReturnsTopTags()
    {
        for ($i = 1; $i <= 40; $i++) {
            Tag::create(['name' => "Tag $i", 'slug' => "tag-$i"]);
        }
        $response = $this->get('/api/popular-tags');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(30, $data['data']['tags']);
    }

    public function testFeaturedReturnsFeaturedTags()
    {
        Tag::create(['name' => 'Featured 1', 'slug' => 'featured-1', 'is_featured' => true]);
        Tag::create(['name' => 'Regular', 'slug' => 'regular', 'is_featured' => false]);
        Tag::create(['name' => 'Featured 2', 'slug' => 'featured-2', 'is_featured' => true]);
        $response = $this->get('/api/featured-tags');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']['tags']);
    }

    public function testCloudReturnsTagCloud()
    {
        for ($i = 1; $i <= 120; $i++) {
            Tag::create(['name' => "Tag $i", 'slug' => "tag-$i", 'usage_count' => 10]);
        }
        $response = $this->get('/api/tags/cloud');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(100, $data['data']['tags']);
    }

    public function testCleanupRemovesUnusedTags()
    {
        Tag::create(['name' => 'Unused Tag', 'slug' => 'unused']);
        $response = $this->post('/api/tags/cleanup');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Cleaned up', $data['message']);
    }

    public function testCheckDeleteTagReturnsCanDeleteWhenNoPagesExist()
    {
        // Arrange: create an author with no pages
        $category = Tag::create([
            'name' => 'Lonely Author',
            'slug' => 'lonely-author',
        ]);

        // Act
        $response = $this->get("/api/tags/{$category->id}/check-delete");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('can_delete', $data['data']);
        $this->assertTrue($data['data']['can_delete']);
        $this->assertEquals(0, $data['data']['pages_count']);
        $this->assertFalse($data['data']['requires_reassignment']);
    }

    public function testCheckDeleteTagReturnsRequiresReassignmentWhenPagesExist()
    {
        // Arrange: create an author that has pages
        $category = Tag::create([
            'name' => 'Author With Pages',
            'slug' => 'author-with-pages',
        ]);

        // Create one or more pages for this author
        $page = \App\Models\Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
        ]);

        PageTag::create([
            'page_id' => $page->id,
            'tag_id' => $category->id,
        ]);

        // Act
        $response = $this->get("/api/tags/{$category->id}/check-delete");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['requires_reassignment']);
        $this->assertGreaterThan(0, $data['data']['pages_count']);
        $this->assertArrayHasKey('alternatives', $data['data']);
        $this->assertIsArray($data['data']['alternatives']);
    }

    public function testCheckDeleteTagReturns404WhenAuthorNotFound()
    {
        // Act
        $response = $this->get('/api/tags/9999/check-delete');

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Tag not found', $data['data']['message']);
    }

    public function testDuplicateTagSuccessfully(): void
    {
        $tag = Tag::create([
            'name' => 'PHP',
            'description' => 'PHP programming',
            'slug' => 'php',
            'status' => 'active'
        ]);

        $response = $this->postJson("/api/tags/{$tag->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('PHP (Copy)', $data['data']['name']);
        $this->assertEquals('PHP programming', $data['data']['description']);
    }

    public function testDuplicateTagWithPages(): void
    {
        $tag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'status' => 'active'
        ]);

        // Create pages with this tag
        $page = Page::create([
            'title' => 'Laravel Tutorial',
            'slug' => 'laravel-tutorial',
            'status' => 'published'
        ]);

        // Associate tag with page using the pivot table directly
        PageTag::create([
            'page_id' => $page->id,
            'tag_id' => $tag->id,
        ]);

        $response = $this->postJson("/api/tags/{$tag->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        // Verify original tag still has the page
        $originalTagPages = PageTag::where('tag_id', $tag->id)->count();
        $this->assertEquals(1, $originalTagPages);

        // Verify new tag has no pages
        $newTag = Tag::find($data['data']['id']);
        $newTagPages = PageTag::where('tag_id', $newTag->id)->count();
        $this->assertEquals(0, $newTagPages);
    }
}