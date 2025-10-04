<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\PageMetadata;
use App\Models\Tag;

class PageControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testIndexReturnsPagesList()
    {
        Page::create(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'published']);
        $response = $this->get('/api/pages');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    public function testIndexWithSearchCriteria()
    {
        Page::create(['title' => 'Published Page', 'slug' => 'published', 'status' => 'published']);
        Page::create(['title' => 'Draft Page', 'slug' => 'draft', 'status' => 'draft']);
        $response = $this->get('/api/pages?status=published');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['items']);
    }

    public function testStoreCreatesNewPage()
    {
        $pageData = [
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'draft', 'author' => 1],
                'seo' => ['meta_title' => 'Page Title', 'meta_description' => 'Description']
            ],
            'blocks' => [['type' => 'text', 'paragraphs' => ['Hello World', 'type' => 'text'], 'order' => 1]]
        ];
        $response = $this->post('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('New Page', $data['data']['page']['title']);
        //$this->assertCount(1, $data['data']['page']['blocks']);
    }

    public function testStoreWithAllFormData()
    {
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech', 'is_active' => true]);
        $tag = Tag::create(['name' => 'PHP', 'slug' => 'php']);
        $pageData = [
            'forms' => [
                'main' => ['title' => 'Complete Page'],
                'meta' => ['slug' => 'complete-page', 'status' => 'published', 'author' => 1, 'publish_date' => '2025-01-01 00:00:00', 'featured' => true],
                'tags' => ['categories' => [$category->id], 'tags' => [$tag->id]],
                'seo' => ['meta_title' => 'SEO Title', 'meta_description' => 'SEO Description', 'meta_keywords' => 'php, tech'],
                'settings' => ['template' => 'default', 'menu_order' => 1],
                'social' => ['enable_sharing' => true, 'platforms' => ['facebook', 'twitter']]
            ],
            'blocks' => []
        ];
        $response = $this->post('/api/pages', $pageData);
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Complete Page', $data['data']['page']['title']);
        $this->assertNotNull($data['data']['page']['metadata']);
        $this->assertNotNull($data['data']['page']['seo']);
    }

    public function testShowReturnsPageById()
    {
        $page = Page::create(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'published']);
        $response = $this->get("/api/pages/{$page->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test Page', $data['data']['title']);
    }

    public function testShowReturnsPageBySlug()
    {
        Page::create(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'published']);
        $response = $this->get('/api/pages/test-page');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test Page', $data['data']['title']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->get('/api/pages/999');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesExistingPage()
    {
        $page = Page::create(['title' => 'Original Title', 'slug' => 'original', 'status' => 'draft']);
        $updateData = ['forms' => ['main' => ['title' => 'Updated Title'], 'meta' => ['slug' => 'updated', 'status' => 'published']]];
        $response = $this->put("/api/pages/{$page->id}", $updateData);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Title', $data['data']['page']['title']);
        $this->assertEquals('published', $data['data']['page']['status']);
    }

    public function testUpdateReplacesBlocks()
    {
        $page = Page::create(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'published']);
        $updateData = [
            'forms' => ['main' => ['title' => 'Test Page']],
            'blocks' => [
                ['type' => 'text', 'paragraphs' => ['New content'], 'order' => 1],
                ['type' => 'image', 'url' => 'image.jpg', 'src' => 'test.jpg', 'alt' => 'test', 'order' => 2]
            ]
        ];
        $response = $this->put("/api/pages/{$page->id}", $updateData);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']['page']['blocks']);
    }

    public function testDestroyDeletesPage()
    {
        $page = Page::create(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'published']);
        $response = $this->delete("/api/pages/{$page->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Page::find($page->id));
    }

    public function testDestroyReturns404ForNonexistent()
    {
        $response = $this->delete('/api/pages/999');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testBulkUpdatePages()
    {
        $page1 = Page::create(['title' => 'Page 1', 'slug' => 'page-1', 'status' => 'draft']);
        $page2 = Page::create(['title' => 'Page 2', 'slug' => 'page-2', 'status' => 'draft']);
        $response = $this->post('/api/pages/bulk-update', [
            'page_ids' => [$page1->id, $page2->id],
            'data' => ['forms' => ['meta' => ['status' => 'published']]]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('results', $data['data']);
    }

    public function testDuplicatePage()
    {
        $page = Page::create(['title' => 'Original Page', 'slug' => 'original-page', 'status' => 'published']);
        $response = $this->post("/api/pages/{$page->id}/duplicate");
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsString('Copy', $data['data']['page']['title']);
        $this->assertEquals('draft', $data['data']['page']['status']);
    }

    public function testDuplicateReturns404ForNonexistent()
    {
        $response = $this->post('/api/pages/999/duplicate');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetFeaturedPages()
    {
        $page1 = Page::create(['title' => 'Featured 1', 'slug' => 'featured-1', 'status' => 'published']);
        $page2 = Page::create(['title' => 'Featured 2', 'slug' => 'featured-2', 'status' => 'published']);

        PageMetadata::create(['page_id' => $page1->id, 'featured' => true]);
        PageMetadata::create(['page_id' => $page2->id, 'featured' => true]);

        $response = $this->get('/api/featured-pages');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('pages', $data['data']);
    }
}