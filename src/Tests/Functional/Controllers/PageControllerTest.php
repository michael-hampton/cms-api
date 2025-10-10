<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Block;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageCategory;
use App\Models\PageMetadata;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
use App\Models\PageTag;
use App\Models\Tag;

class PageControllerTest extends FunctionalTestCase
{
    public function testIndexReturnsPagesList()
    {
        Page::create(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'published', 'site_id' => $this->siteId]);;
        $response = $this->getForSite('/api/pages');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    public function testIndexWithSearchCriteria()
    {
        Page::create(['title' => 'Published Page', 'slug' => 'published', 'status' => 'published']);
        Page::create(['title' => 'Draft Page', 'slug' => 'draft', 'status' => 'draft']);
        $response = $this->getForSite('/api/pages?status=published');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['items']);
    }

    public function testStoreCreatesNewPage()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'draft', 'author' => 1],
                'seo' => ['meta_title' => 'Page Title', 'meta_description' => 'Description']
            ],
            'blocks' => [['type' => 'text', 'paragraphs' => ['Hello World', 'type' => 'text'], 'order' => 1]]
        ];
        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('New Page', $data['data']['page']['title']);
        //$this->assertCount(1, $data['data']['page']['blocks']);
    }

    public function testStoreWithAllFormData()
    {
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech', 'is_active' => true, 'site_id' => $this->siteId]);;
        $tag = Tag::create(['name' => 'PHP', 'slug' => 'php', 'site_id' => $this->siteId]);;;
        $pageData = [
            'site_id' => $this->siteId,
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
        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Complete Page', $data['data']['page']['title']);
        $this->assertNotNull($data['data']['page']['metadata']);
        $this->assertNotNull($data['data']['page']['seo']);
    }

    public function testShowReturnsPageById()
    {
        $page = Page::create(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'published']);
        $response = $this->getForSite("/api/pages/{$page->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test Page', $data['data']['title']);
    }

    public function testShowReturnsPageBySlug()
    {
        Page::create(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'published', 'site_id' => $this->siteId]);;
        $response = $this->getForSite('/api/pages/test-page');
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
        $page = Page::create(['title' => 'Original Title', 'slug' => 'original', 'status' => 'draft', 'site_id' => $this->siteId]);;
        $updateData = ['forms' => ['main' => ['title' => 'Updated Title'], 'meta' => ['slug' => 'updated', 'status' => 'published']], 'site_id' => $this->siteId];
        $response = $this->putForSite("/api/pages/{$page->id}", $updateData);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Title', $data['data']['page']['title']);
        $this->assertEquals('published', $data['data']['page']['status']);
    }

    public function testUpdateReplacesBlocks()
    {
        $page = Page::create(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'published', 'site_id' => $this->siteId]);;
        $updateData = [
            'site_id' => $this->siteId,
            'forms' => ['main' => ['title' => 'Test Page']],
            'blocks' => [
                ['type' => 'text', 'paragraphs' => ['New content'], 'order' => 1],
                ['type' => 'image', 'url' => 'image.jpg', 'src' => 'test.jpg', 'alt' => 'test', 'order' => 2]
            ]
        ];
        $response = $this->putForSite("/api/pages/{$page->id}", $updateData);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']['page']['blocks']);
    }

    public function testDestroyDeletesPage()
    {
        $page = Page::create(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'published', 'site_id' => $this->siteId]);
        $response = $this->deleteForSite("/api/pages/{$page->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Page::find($page->id));
    }

    public function testDestroyReturns404ForNonexistent()
    {
        $response = $this->deleteForSite('/api/pages/999');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testBulkUpdatePages()
    {
        $page1 = Page::create(['title' => 'Page 1', 'slug' => 'page-1', 'status' => 'draft']);
        $page2 = Page::create(['title' => 'Page 2', 'slug' => 'page-2', 'status' => 'draft']);
        $response = $this->postForSite('/api/pages/bulk-update', [
            'site_id' => $this->siteId,
            'page_ids' => [$page1->id, $page2->id],
            'data' => ['forms' => ['meta' => ['status' => 'published']]]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('results', $data['data']);
    }

    public function testDuplicatePage()
    {
        $page = Page::create(['title' => 'Original Page', 'slug' => 'original-page', 'status' => 'published', 'site_id' => $this->siteId]);
        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsString('Copy', $data['data']['page']['title']);
        $this->assertEquals('draft', $data['data']['page']['status']);
    }

    public function testDuplicateReturns404ForNonexistent()
    {
        $response = $this->postForSite('/api/pages/999/duplicate');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetFeaturedPages()
    {
        $page1 = Page::create(['title' => 'Featured 1', 'slug' => 'featured-1', 'status' => 'published']);
        $page2 = Page::create(['title' => 'Featured 2', 'slug' => 'featured-2', 'status' => 'published']);

        PageMetadata::create(['page_id' => $page1->id, 'featured' => true]);
        PageMetadata::create(['page_id' => $page2->id, 'featured' => true]);

        $response = $this->getForSite('/api/featured-pages');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('pages', $data['data']);
    }

    public function testDuplicatePageClonesAllRelations()
    {
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech', 'is_active' => true]);
        $tag = Tag::create(['name' => 'PHP', 'slug' => 'php']);

        $page = Page::create([
            'title' => 'Original Page',
            'slug' => 'original-page',
            'status' => 'published',
            'meta_title' => 'Original Meta',
            'meta_description' => 'Original Description',
            'site_id' => $this->siteId
        ]);

        // Create metadata
        PageMetadata::create([
            'page_id' => $page->id,
            'content_type' => 'article',
            'author' => 1,
            'featured' => true,
            'allow_comments' => true
        ]);

        // Create SEO
        PageSeo::create([
            'page_id' => $page->id,
            'meta_keywords' => 'test, keywords',
            'canonical_url' => 'https://example.com/original',
            'no_index' => false
        ]);

        // Create settings
        PageSettings::create([
            'page_id' => $page->id,
            'template' => 'custom',
            'menu_order' => 5
        ]);

        // Create social
        PageSocial::create([
            'page_id' => $page->id,
            'enable_sharing' => true,
            'platforms' => json_encode(['facebook', 'twitter'])
        ]);

        // Create block
        Block::create([
            'page_id' => $page->id,
            'type' => 'text',
            'data' => json_encode(['content' => 'Test content']),
            'order' => 1
        ]);

        // Associate categories and tags
        PageCategory::create([
            'page_id' => $page->id,
            'category_id' => $category->id
        ]);

        PageTag::create([
            'page_id' => $page->id,
            'tag_id' => $tag->id
        ]);

        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Verify page duplication
        $this->assertStringContainsString('Copy', $data['data']['page']['title']);
        $this->assertEquals('draft', $data['data']['page']['status']);

        $duplicatedPage = $data['data']['page'];

        // Verify metadata was duplicated
        $this->assertNotNull($duplicatedPage['metadata']);
        $this->assertEquals('article', $duplicatedPage['metadata']['content_type']);
        $this->assertEquals(1, $duplicatedPage['metadata']['featured']);

        // Verify SEO was duplicated
        $this->assertNotNull($duplicatedPage['seo']);
        $this->assertEquals('test, keywords', $duplicatedPage['seo']['meta_keywords']);

        // Verify settings was duplicated
        $this->assertNotNull($duplicatedPage['settings']);
        $this->assertEquals('custom', $duplicatedPage['settings']['template']);
        $this->assertEquals(5, $duplicatedPage['settings']['menu_order']);

        // Verify social was duplicated
        $this->assertNotNull($duplicatedPage['social']);
        $this->assertEquals(1, $duplicatedPage['social']['enable_sharing']);

        // Verify blocks were duplicated
        $this->assertCount(1, $duplicatedPage['blocks']);
        $this->assertEquals('text', $duplicatedPage['blocks'][0]['type']);

        // Verify categories were duplicated
        $this->assertCount(1, $duplicatedPage['categories']);
        $this->assertEquals($category->id, $duplicatedPage['categories'][0]['id']);

        // Verify tags were duplicated
        $this->assertCount(1, $duplicatedPage['tags']);
        $this->assertEquals($tag->id, $duplicatedPage['tags'][0]['id']);
    }

    public function testDuplicatePageWithMultipleBlocks()
    {
        $page = Page::create([
            'title' => 'Page with Blocks',
            'slug' => 'page-with-blocks',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        Block::create([
            'page_id' => $page->id,
            'type' => 'text',
            'data' => json_encode(['content' => 'First block']),
            'order' => 1
        ]);

        Block::create([
            'page_id' => $page->id,
            'type' => 'image',
            'data' => json_encode(['url' => 'image.jpg', 'alt' => 'Test']),
            'order' => 2
        ]);

        Block::create([
            'page_id' => $page->id,
            'type' => 'text',
            'data' => json_encode(['content' => 'Third block']),
            'order' => 3
        ]);

        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(3, $data['data']['page']['blocks']);
        $this->assertEquals('text', $data['data']['page']['blocks'][0]['type']);
        $this->assertEquals('image', $data['data']['page']['blocks'][1]['type']);
        $this->assertEquals('text', $data['data']['page']['blocks'][2]['type']);
    }

    public function testDuplicatePageWithMultipleCategoriesAndTags()
    {
        $cat1 = Category::create(['name' => 'Tech', 'slug' => 'tech', 'is_active' => true, 'site_id' => $this->siteId]);;
        $cat2 = Category::create(['name' => 'News', 'slug' => 'news', 'is_active' => true, 'site_id' => $this->siteId]);;;
        $tag1 = Tag::create(['name' => 'PHP', 'slug' => 'php', 'site_id' => $this->siteId]);;;;
        $tag2 = Tag::create(['name' => 'Testing', 'slug' => 'testing', 'site_id' => $this->siteId]);

        $page = Page::create([
            'title' => 'Multi Category Page',
            'slug' => 'multi-category',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageCategory::create(['page_id' => $page->id, 'category_id' => $cat1->id]);
        PageCategory::create(['page_id' => $page->id, 'category_id' => $cat2->id]);
        PageTag::create(['page_id' => $page->id, 'tag_id' => $tag1->id]);
        PageTag::create(['page_id' => $page->id, 'tag_id' => $tag2->id]);

        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']['page']['categories']);
        $this->assertCount(2, $data['data']['page']['tags']);
    }

    public function testDuplicatePageReturns404ForNonexistent()
    {
        $response = $this->postForSite('/api/pages/999/duplicate');
        $this->assertEquals(404, $response->getStatusCode());
    }
}