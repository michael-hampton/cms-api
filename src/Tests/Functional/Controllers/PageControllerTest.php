<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Author;
use App\Models\Block;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCategory;
use App\Models\PageHistory;
use App\Models\PageMetadata;
use App\Models\PageRegionSet;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
use App\Models\PageTag;
use App\Models\PageTerritory;
use App\Models\RegionSet;
use App\Models\Site;
use App\Models\Tag;
use App\Models\Territory;

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
        Page::create(['title' => 'Published Page', 'slug' => 'published', 'status' => 'published', 'site_id' => $this->siteId]);
        Page::create(['title' => 'Draft Page', 'slug' => 'draft', 'status' => 'draft', 'site_id' => $this->siteId]);;
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
        $page = Page::create(['title' => 'Original Title', 'slug' => 'original', 'status' => 'published', 'site_id' => $this->siteId]);;
        $updateData = ['status' => 'published', 'forms' => ['main' => ['title' => 'Updated Title'], 'meta' => ['slug' => 'updated']], 'site_id' => $this->siteId];
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

    public function testStoreCreatesHistoryEntry()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'draft', 'author' => 1],
                'seo' => ['meta_title' => 'Page Title', 'meta_description' => 'Description']
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $pageId = $data['data']['page']['id'];

        // Verify history entry was created
        $history = PageHistory::where('page_id', $pageId)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('created', $history->action);
    }

    public function testUpdateToPublishedCreatesPublishHistoryEntry()
    {
        $page = Page::create([
            'title' => 'Draft Page',
            'slug' => 'draft-page',
            'status' => 'draft',
            'site_id' => $this->siteId
        ]);

        $updateData = [
            'id' => $page->id,
            'status' => 'published',
            'forms' => [
                'main' => ['title' => 'Draft Page'],
                'meta' => ['slug' => 'draft-page', 'status' => 'published']
            ],
            'site_id' => $this->siteId
        ];

        $response = $this->putForSite("/api/pages/{$page->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify publish history entry was created
        $history = PageHistory::where('page_id', $page->id)
            ->where('action', 'published')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('published', $history->action);
    }

    public function testUpdateToDraftCreatesUnpublishHistoryEntry()
    {
        $page = Page::create([
            'title' => 'Published Page',
            'slug' => 'published-page',
            'status' => 'published',
            'site_id' => $this->siteId,
            'published_at' => date('Y-m-d H:i:s')
        ]);

        $updateData = [
            'id' => $page->id,
            'forms' => [
                'main' => ['title' => 'Published Page'],
                'meta' => ['slug' => 'published-page', 'status' => 'draft']
            ],
            'site_id' => $this->siteId
        ];

        $response = $this->putForSite("/api/pages/{$page->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify unpublish history entry was created
        $history = PageHistory::where('page_id', $page->id)
            ->where('action', 'unpublished')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('unpublished', $history->action);
    }

    public function testDestroyCreatesDeleteHistoryEntry()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $pageId = $page->id;

        $response = $this->deleteForSite("/api/pages/{$page->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(Page::find($pageId));

        // Verify delete history entry exists (even though page is deleted)
        $history = PageHistory::where('page_id', $pageId)
            ->where('action', 'deleted')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('deleted', $history->action);
    }

    public function testDuplicateCreatesHistoryEntries()
    {
        $page = Page::create([
            'title' => 'Original Page',
            'slug' => 'original-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $newPageId = $data['data']['page']['id'];

        // Verify duplication history entry was created for the new page
        $history = PageHistory::where('page_id', $newPageId)
            ->where('action', 'duplicated')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('duplicated', $history->action);
    }

    public function testStoreWithMultipleAuthors()
    {
        $author1 = $this->createAuthor();
        $author2 = $this->createAuthor();
        $author3 = $this->createAuthor();
        $pageData = [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'Multi-Author Page', 'authors' => [1, 2]],
                'meta' => [
                    'slug' => 'multi-author',
                    'status' => 'draft',$author3->id,
                    'authors' => [$author1->id, $author2->id],
                    'contributors' => [$author3->id]
                ]
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Multi-Author Page', $data['data']['page']['title']);
        $this->assertCount(3, $data['data']['page']['authors']);
    }

    public function testStoreWithMultipleRegionSetsAndTerritories()
    {
        $regionSet1 = RegionSet::create([
            'name' => 'North America',
            'slug' => 'north-america',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $regionSet2 = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'is_active' => true,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

        $territory1 = Territory::create([
            'name' => 'USA',
            'code' => 'US',
            'region_set_id' => $regionSet1->id,
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $territory2 = Territory::create([
            'name' => 'UK',
            'code' => 'GB',
            'region_set_id' => $regionSet2->id,
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $pageData = [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'Regional Page'],
                'meta' => [
                    'slug' => 'regional-page',
                    'status' => 'draft',
                    'region_sets' => [$regionSet1->id, $regionSet2->id],
                    'territories' => [$territory1->id, $territory2->id]
                ]
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Regional Page', $data['data']['page']['title']);
        $this->assertCount(2, $data['data']['page']['regionSets']);
        $this->assertCount(2, $data['data']['page']['territories']);
    }

    public function testDuplicatePageClonesRegionSetsAndTerritories()
    {
        $regionSet = RegionSet::create([
            'name' => 'Test Region',
            'slug' => 'test-region',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $territory = Territory::create([
            'name' => 'Test Territory',
            'code' => 'TT',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $page = Page::create([
            'title' => 'Original Page',
            'slug' => 'original-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageRegionSet::create([
            'page_id' => $page->id,
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        PageTerritory::create([
            'page_id' => $page->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']['page']['regionSets']);
        $this->assertCount(1, $data['data']['page']['territories']);
        $this->assertEquals($regionSet->id, $data['data']['page']['regionSets'][0]['id']);
        $this->assertEquals($territory->id, $data['data']['page']['territories'][0]['id']);
    }

    public function testDuplicatePageClonesAuthorsAndContributors()
    {
        $page = Page::create([
            'title' => 'Original Page',
            'slug' => 'original-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $author1 = $this->createAuthor();
        $author2 = $this->createAuthor();
        $author3 = $this->createAuthor();

        // Create authors
        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author1->id,
            'role' => 'primary',
            'sort_order' => 0
        ]);

        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author2->id,
            'role' => 'primary',
            'sort_order' => 1
        ]);

        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author3->id,
            'role' => 'contributor',
            'sort_order' => 0
        ]);

        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $duplicatedPageId = $data['data']['page']['id'];

        // Verify authors were duplicated
        $primaryAuthors = PageAuthor::where('page_id', $duplicatedPageId)
            ->where('role', 'primary')
            ->get();
        $this->assertCount(2, $primaryAuthors);

        // Verify contributors were duplicated
        $contributors = PageAuthor::where('page_id', $duplicatedPageId)
            ->where('role', 'contributor')
            ->get();
        $this->assertCount(1, $contributors);
    }

    private function createAuthor()
    {
        return Author::create([
            'name' => '<NAME>',
            'email' => '<EMAIL>',
            'slug' => 'test-author-'.date('YmdHis'),
            'status' => 'active',
        ]);
    }

    public function testCloneToSiteCreatesPageInDifferentSite()
    {
        $sourcePage = Page::create([
            'title' => 'Source Page',
            'slug' => 'source-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($targetSiteId, $data['data']['page']['site_id']);
        $this->assertEquals('draft', $data['data']['page']['status']);
    }

    public function testCloneToSiteWithCustomTitle()
    {
        $sourcePage = Page::create([
            'title' => 'Original Title',
            'slug' => 'original',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId,
            'title' => 'Custom Title'
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Custom Title', $data['data']['page']['title']);
    }

    public function testCloneToSiteClonesAllRelations()
    {
        $category = Category::create([
            'name' => 'Tech',
            'slug' => 'tech',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $tag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'site_id' => $this->siteId
        ]);

        $author = $this->createAuthor();

        $sourcePage = Page::create([
            'title' => 'Complete Page',
            'slug' => 'complete-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        // Add metadata
        PageMetadata::create([
            'page_id' => $sourcePage->id,
            'content_type' => 'article',
            'featured' => true
        ]);

        // Add SEO
        PageSeo::create([
            'page_id' => $sourcePage->id,
            'meta_keywords' => 'test, keywords'
        ]);

        // Add settings
        PageSettings::create([
            'page_id' => $sourcePage->id,
            'template' => 'custom'
        ]);

        // Add social
        PageSocial::create([
            'page_id' => $sourcePage->id,
            'enable_sharing' => true
        ]);

        // Add blocks
        Block::create([
            'page_id' => $sourcePage->id,
            'type' => 'text',
            'data' => json_encode(['content' => 'Test']),
            'order' => 1
        ]);

        // Add category and tag
        PageCategory::create([
            'page_id' => $sourcePage->id,
            'category_id' => $category->id
        ]);

        PageTag::create([
            'page_id' => $sourcePage->id,
            'tag_id' => $tag->id
        ]);

        // Add author
        PageAuthor::create([
            'page_id' => $sourcePage->id,
            'author_id' => $author->id,
            'role' => 'primary',
            'sort_order' => 0
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $clonedPage = $data['data']['page'];

        // Verify all relations were cloned
        $this->assertNotNull($clonedPage['metadata']);
        $this->assertEquals('article', $clonedPage['metadata']['content_type']);

        $this->assertNotNull($clonedPage['seo']);
        $this->assertEquals('test, keywords', $clonedPage['seo']['meta_keywords']);

        $this->assertNotNull($clonedPage['settings']);
        $this->assertEquals('custom', $clonedPage['settings']['template']);

        $this->assertNotNull($clonedPage['social']);
        $this->assertEquals(1, $clonedPage['social']['enable_sharing']);

        $this->assertCount(1, $clonedPage['blocks']);
        $this->assertEquals('text', $clonedPage['blocks'][0]['type']);

        $this->assertCount(1, $clonedPage['categories']);
        $this->assertCount(1, $clonedPage['tags']);
        $this->assertCount(1, $clonedPage['authors']);
    }

    public function testCloneToSiteReusesExistingCategory()
    {
        // Create category in source site
        $sourceCategory = Category::create([
            'name' => 'Tech',
            'slug' => 'tech',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $sourcePage = Page::create([
            'title' => 'Source Page',
            'slug' => 'source-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageCategory::create([
            'page_id' => $sourcePage->id,
            'category_id' => $sourceCategory->id
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        // Create category with same slug in target site
        $targetCategory = Category::create([
            'name' => 'Tech',
            'slug' => 'tech',
            'is_active' => true,
            'site_id' => $targetSiteId
        ]);

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Verify the existing category was reused
        $this->assertCount(1, $data['data']['page']['categories']);
        $this->assertEquals($targetCategory->id, $data['data']['page']['categories'][0]['id']);

        // Verify no duplicate category was created
        $categoryCount = Category::where('slug', 'tech')
            ->where('site_id', $targetSiteId)
            ->count();
        $this->assertEquals(1, $categoryCount);
    }

    public function testCloneToSiteReusesExistingTag()
    {
        $sourceTag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'site_id' => $this->siteId
        ]);

        $sourcePage = Page::create([
            'title' => 'Source Page',
            'slug' => 'source-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageTag::create([
            'page_id' => $sourcePage->id,
            'tag_id' => $sourceTag->id
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        // Create tag with same slug in target site
        $targetTag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'site_id' => $targetSiteId
        ]);

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Verify the existing tag was reused
        $this->assertCount(1, $data['data']['page']['tags']);
        $this->assertEquals($targetTag->id, $data['data']['page']['tags'][0]['id']);

        // Verify no duplicate tag was created
        $tagCount = Tag::where('slug', 'php')
            ->where('site_id', $targetSiteId)
            ->count();
        $this->assertEquals(1, $tagCount);
    }

    public function testCloneToSiteReusesExistingAuthor()
    {
        $sourceAuthor = $this->createAuthor();

        $sourcePage = Page::create([
            'title' => 'Source Page',
            'slug' => 'source-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageAuthor::create([
            'page_id' => $sourcePage->id,
            'author_id' => $sourceAuthor->id,
            'role' => 'primary',
            'sort_order' => 0
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        // Create author with same slug in target site
        $targetAuthor = Author::create([
            'name' => $sourceAuthor->name,
            'email' => 'different@example.com',
            'slug' => $sourceAuthor->slug,
            'status' => 'active',
            'site_id' => $targetSiteId
        ]);

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Verify the existing author was reused
        $this->assertCount(1, $data['data']['page']['authors']);
        $this->assertEquals($targetAuthor->id, $data['data']['page']['authors'][0]['id']);

        // Verify no duplicate author was created
        $authorCount = Author::where('slug', $sourceAuthor->slug)
            ->where('site_id', $targetSiteId)
            ->count();
        $this->assertEquals(1, $authorCount);
    }

    public function testCloneToSiteGeneratesUniqueSlug()
    {
        $sourcePage = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        // Create page with same slug in target site
        Page::create([
            'title' => 'Existing Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $targetSiteId
        ]);

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Verify a unique slug was generated
        $this->assertNotEquals('test-page', $data['data']['page']['slug']);
        $this->assertStringStartsWith('test-page-', $data['data']['page']['slug']);
    }

    public function testCloneToSiteWithRegionSetsAndTerritories()
    {
        $regionSet = RegionSet::create([
            'name' => 'North America',
            'slug' => 'north-america',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $territory = Territory::create([
            'name' => 'USA',
            'code' => 'US',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $sourcePage = Page::create([
            'title' => 'Regional Page',
            'slug' => 'regional-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageRegionSet::create([
            'page_id' => $sourcePage->id,
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        PageTerritory::create([
            'page_id' => $sourcePage->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']['page']['regionSets']);
        $this->assertCount(1, $data['data']['page']['territories']);
    }

    public function testCloneToSiteReturns404ForNonexistentPage()
    {
        $response = $this->postForSite('/api/pages/999/clone-to-site', [
            'target_site_id' => $this->siteId + 1
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCloneToSiteReturns422WithoutTargetSiteId()
    {
        $sourcePage = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", []);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('target_site_id is required', $data['error']);
    }

    public function testCloneToSiteCreatesHistoryEntry()
    {
        $sourcePage = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $clonedPageId = $data['data']['page']['id'];

        // Verify history entry was created
        $history = PageHistory::where('page_id', $clonedPageId)
            ->where('action', 'cloned_to_site')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('cloned_to_site', $history->action);

        $this->assertEquals($sourcePage->id, $history->changes['source_page_id']);
        $this->assertEquals($targetSiteId, $history->changes['target_site_id']);
    }

    public function testCloneToSiteHandlesMultipleBlocks()
    {
        $sourcePage = Page::create([
            'title' => 'Multi Block Page',
            'slug' => 'multi-block',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        Block::create([
            'page_id' => $sourcePage->id,
            'type' => 'text',
            'data' => json_encode(['content' => 'Block 1']),
            'order' => 1
        ]);

        Block::create([
            'page_id' => $sourcePage->id,
            'type' => 'image',
            'data' => json_encode(['url' => 'image.jpg', 'alt' => 'Test']),
            'order' => 2
        ]);

        Block::create([
            'page_id' => $sourcePage->id,
            'type' => 'text',
            'data' => json_encode(['content' => 'Block 3']),
            'order' => 3
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(3, $data['data']['page']['blocks']);
        $this->assertEquals('text', $data['data']['page']['blocks'][0]['type']);
        $this->assertEquals('image', $data['data']['page']['blocks'][1]['type']);
        $this->assertEquals('text', $data['data']['page']['blocks'][2]['type']);
    }

    public function testCloneToSiteWithMultipleCategoriesAndTags()
    {
        $cat1 = Category::create([
            'name' => 'Tech',
            'slug' => 'tech',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $cat2 = Category::create([
            'name' => 'News',
            'slug' => 'news',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $tag1 = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'site_id' => $this->siteId
        ]);

        $tag2 = Tag::create([
            'name' => 'Testing',
            'slug' => 'testing',
            'site_id' => $this->siteId
        ]);

        $sourcePage = Page::create([
            'title' => 'Multi Taxonomy Page',
            'slug' => 'multi-taxonomy',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageCategory::create(['page_id' => $sourcePage->id, 'category_id' => $cat1->id]);
        PageCategory::create(['page_id' => $sourcePage->id, 'category_id' => $cat2->id]);
        PageTag::create(['page_id' => $sourcePage->id, 'tag_id' => $tag1->id]);
        PageTag::create(['page_id' => $sourcePage->id, 'tag_id' => $tag2->id]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']['page']['categories']);
        $this->assertCount(2, $data['data']['page']['tags']);
    }

    public function testIndexFiltersByAuthor()
    {
        $author1 = Author::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'slug' => 'john-doe',
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $author2 = Author::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'slug' => 'jane-smith',
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $page1 = Page::create([
            'title' => 'Page by John',
            'slug' => 'page-by-john',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Page by Jane',
            'slug' => 'page-by-jane',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageAuthor::create([
            'page_id' => $page1->id,
            'author_id' => $author1->id,
            'role' => 'primary',
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);

        PageAuthor::create([
            'page_id' => $page2->id,
            'author_id' => $author2->id,
            'role' => 'primary',
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/pages?author={$author1->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Page by John', $data['items'][0]['title']);
    }

    public function testIndexFiltersByMultipleAuthors()
    {
        $author1 = Author::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'slug' => 'john-doe',
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $author2 = Author::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'slug' => 'jane-smith',
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $author3 = Author::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'slug' => 'bob-johnson',
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $page1 = Page::create(['title' => 'Page 1', 'slug' => 'page-1', 'status' => 'published', 'site_id' => $this->siteId]);
        $page2 = Page::create(['title' => 'Page 2', 'slug' => 'page-2', 'status' => 'published', 'site_id' => $this->siteId]);
        $page3 = Page::create(['title' => 'Page 3', 'slug' => 'page-3', 'status' => 'published', 'site_id' => $this->siteId]);

        PageAuthor::create(['page_id' => $page1->id, 'author_id' => $author1->id, 'role' => 'primary', 'sort_order' => 0, 'site_id' => $this->siteId]);
        PageAuthor::create(['page_id' => $page2->id, 'author_id' => $author2->id, 'role' => 'primary', 'sort_order' => 0, 'site_id' => $this->siteId]);
        PageAuthor::create(['page_id' => $page3->id, 'author_id' => $author3->id, 'role' => 'primary', 'sort_order' => 0, 'site_id' => $this->siteId]);

        $response = $this->getForSite("/api/pages?author={$author1->id},{$author2->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['items']);
    }


    private function createSite() {
        return Site::create([
            'name' => 'Test Site 2',
            'slug' => 'test-site-2',
            'is_active' => true,
            'is_default' => false,
        ]);
    }
}