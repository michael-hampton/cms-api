<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Author;
use App\Models\Block;
use App\Models\Category;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageHistory;
use App\Models\Site;
use App\Models\Tag;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsPagesList()
    {
        $this->createPage();
        $response = $this->getForSite('/api/pages');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    public function testIndexWithSearchCriteria()
    {
       $this->createPage(['status' => 'published']);
        $this->createPage(['status' => 'draft']);

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
        $category = $this->createCategory();
        $tag = $this->createTag();
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
        $page = $this->createPage();
        $response = $this->getForSite("/api/pages/{$page->id}");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test Page', $data['data']['title']);
    }

    public function testShowReturnsPageBySlug()
    {
       $this->createPage(['slug' => 'test-page']);
        $response = $this->getForSite('/api/pages/test-page');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test Page', $data['data']['title']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->getForSite('/api/pages/999');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesExistingPage()
    {
        $page = $this->createPage();
        $updateData = ['status' => 'published', 'forms' => ['main' => ['title' => 'Updated Title'], 'meta' => ['slug' => 'updated']], 'site_id' => $this->siteId];
        $response = $this->putForSite("/api/pages/{$page->id}", $updateData);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Title', $data['data']['page']['title']);
        $this->assertEquals('published', $data['data']['page']['status']);
    }

    public function testUpdateReplacesBlocks()
    {
        $page = $this->createPage();
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
        $page = $this->createPage();
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
        $page1 = $this->createPage();
        $page2 = $this->createPage();
        $response = $this->postForSite('/api/pages/bulk-update', [
            'title' => 'Updated Title',
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
        $page = $this->createPage();
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
        $page1 = $this->createPage();
        $page2 = $this->createPage();

        $this->createPageMetadata($page1->id, ['featured' => true]);
        $this->createPageMetadata($page2->id, ['featured' => true]);

        $response = $this->getForSite('/api/featured-pages');
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('pages', $data['data']);
    }

    public function testDuplicatePageClonesAllRelations()
    {
        $category = $this->createCategory();
        $tag = $this->createTag();

        $page = $this->createPage();
        $this->createPageMetadata($page->id, ['content_type' => 'article', 'featured' => 1]);;
        $this->createPageSeo($page->id, ['meta_keywords' => 'test, keywords']);;
        $this->createPageSettings($page->id, ['template' => 'custom', 'menu_order' => 5]);;;
        $this->createPageSocial($page->id);

        // Create block
        Block::create([
            'page_id' => $page->id,
            'type' => 'text',
            'data' => json_encode(['content' => 'Test content']),
            'order' => 1
        ]);

        $this->attachCategoryToPage($page, $category);
        $this->attachTagToPage($page, $tag);;

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
        $page = $this->createPage();

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
        $cat1 = $this->createCategory();
        $cat2 = $this->createCategory();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();

        $page = $this->createPage();

        $this->attachCategoryToPage($page, $cat1);
        $this->attachCategoryToPage($page, $cat2);
        $this->attachTagToPage($page, $tag1);
        $this->attachTagToPage($page, $tag2);;

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
        $page = $this->createPage();

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
        $page = $this->createPage();

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
        $regionSet1 = $this->createRegionSet();

        $regionSet2 = $this->createRegionSet();

        $territory1 = $this->createTerritory();

        $territory2 = $this->createTerritory();

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
        $regionSet = $this->createRegionSet();

        $territory = $this->createTerritory();

        $page = $this->createPage();

        $this->attachRegionSetToPage($page, $regionSet);

        $this->attachTerritoryToPage($page, $territory);

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
        $page = $this->createPage();

        $author1 = $this->createAuthor();
        $author2 = $this->createAuthor();
        $author3 = $this->createAuthor();

        $this->attachAuthorToPage($page, $author1, ['role' => 'primary']);
        $this->attachAuthorToPage($page, $author2, ['role' => 'contributor']);
        $this->attachAuthorToPage($page, $author3, ['role' => 'primary']);

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

    public function testCloneToSiteCreatesPageInDifferentSite()
    {
        $sourcePage = $this->createPage();

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
        $sourcePage = $this->createPage();

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
        $category = $this->createCategory();
        $tag = $this->createTag();
        $author = $this->createAuthor();
        $sourcePage = $this->createPage();
        $this->createPageMetadata($sourcePage->id, ['meta_keywords' => 'test, keywords', 'content_type' => 'article']);
        $this->createPageSeo($sourcePage->id, ['meta_keywords' => 'test, keywords', 'meta_description' => 'test description']);;
        $this->createPageSettings($sourcePage->id, ['template' => 'custom']);
        $this->createPageSocial($sourcePage->id, ['enable_sharing' => true]);

        // Add blocks
        $this->createBlock($sourcePage->id);

        $this->attachCategoryToPage($sourcePage, $category);
        $this->attachTagToPage($sourcePage, $tag);

        // Add author
        $this->attachAuthorToPage($sourcePage, $author);

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
        $sourceCategory = $this->createCategory(['name' => 'Tech', 'slug' => 'tech']);;

        $sourcePage = $this->createPage();

        $this->attachCategoryToPage($sourcePage, $sourceCategory);;

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        // Create category with same slug in target site
        $targetCategory = $this->createCategory(['name' => 'Tech', 'slug' => 'tech', 'site_id' => $targetSiteId]);

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
        $sourceTag = $this->createTag();

        $sourcePage = $this->createPage();

        $this->attachTagToPage($sourcePage, $sourceTag);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        // Create tag with same slug in target site
        $targetTag = $this->createTag(['slug' => $sourceTag->slug, 'site_id' => $targetSiteId]);;

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Verify the existing tag was reused
        $this->assertCount(1, $data['data']['page']['tags']);
        $this->assertEquals($targetTag->id, $data['data']['page']['tags'][0]['id']);

        // Verify no duplicate tag was created
        $tagCount = Tag::where('slug', $sourceTag->slug)
            ->where('site_id', $targetSiteId)
            ->count();
        $this->assertEquals(1, $tagCount);
    }

    public function testCloneToSiteReusesExistingAuthor()
    {
        $sourceAuthor = $this->createAuthor();

        $sourcePage = $this->createPage();

        $this->attachAuthorToPage($sourcePage, $sourceAuthor);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        // Create author with same slug in target site
        $targetAuthor = $this->createAuthor(['slug' => $sourceAuthor->slug, 'site_id' => $targetSiteId]);

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
        $sourcePage = $this->createPage();

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        // Create page with same slug in target site

        $this->createPage([
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
        $regionSet = $this->createRegionSet();

        $territory = $this->createTerritory();

        $sourcePage = $this->createPage();

        $this->attachRegionSetToPage($sourcePage, $regionSet);

       $this->attachTerritoryToPage($sourcePage, $territory);

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
        $sourcePage = $this->createPage();

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", []);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('target_site_id is required', $data['error']);
    }

    public function testCloneToSiteCreatesHistoryEntry()
    {
        $sourcePage = $this->createPage();

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
        $sourcePage = $this->createPage();

       $this->createBlock($sourcePage->id, ['type' => 'text']);
        $this->createBlock($sourcePage->id, ['type' => 'image']);
        $this->createBlock($sourcePage->id, ['type' => 'text']);

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
        $cat1 = $this->createCategory();

        $cat2 = $this->createCategory();

        $tag1 = $this->createTag();

        $tag2 = $this->createTag();

        $sourcePage = $this->createPage();

        $this->attachCategoryToPage($sourcePage, $cat1);
        $this->attachCategoryToPage($sourcePage, $cat2);
        $this->attachTagToPage($sourcePage, $tag1);
        $this->attachTagToPage($sourcePage, $tag2);;

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
        $author1 = $this->createAuthor();

        $author2 = $this->createAuthor();

        $page1 = $this->createPage(['title' => 'Page by John']);

        $page2 = $this->createPage();

        $this->attachAuthorToPage($page1, $author1);
        $this->attachAuthorToPage($page2, $author2);

        $response = $this->getForSite("/api/pages?author={$author1->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Page by John', $data['items'][0]['title']);
    }

    public function testIndexFiltersByMultipleAuthors()
    {
        $author1 = $this->createAuthor();

        $author2 = $this->createAuthor();

        $author3 = $this->createAuthor();

        $page1 = $this->createPage();
        $page2 = $this->createPage();
        $page3 = $this->createPage();

        $this->attachAuthorToPage($page1, $author1);
        $this->attachAuthorToPage($page2, $author2);
        $this->attachAuthorToPage($page3, $author3);

        $response = $this->getForSite("/api/pages?author={$author1->id},{$author2->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['items']);
    }

    public function testStoreCreatesGalleryWithSlides()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'Gallery Page'],
                'meta' => ['slug' => 'gallery-page', 'status' => 'draft', 'content_type' => 'gallery']
            ],
            'gallery_slides' => [
                [
                    'id' => 0,
                    'image_id' => 1,
                    'image_url' => 'http://example.com/image1.jpg',
                    'title' => 'Slide 1',
                    'caption' => 'First slide',
                    'alt' => 'Alt text 1',
                    'blocks' => []
                ],
                [
                    'id' => 1,
                    'image_id' => 2,
                    'image_url' => 'http://example.com/image2.jpg',
                    'title' => 'Slide 2',
                    'caption' => 'Second slide',
                    'alt' => 'Alt text 2',
                    'blocks' => []
                ]
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Gallery Page', $data['data']['page']['title']);
        $this->assertEquals('gallery', $data['data']['page']['metadata']['content_type']);
        $this->assertNotNull($data['data']['page']['gallery_slides']);

        $slides = json_decode($data['data']['page']['gallery_slides'], true);
        $this->assertCount(2, $slides);
    }

    public function testDuplicatePageClonesGallerySlides()
    {
        $page = $this->createPage(['page_type' => 'gallery']);

        $slides = [
            [
                'id' => 0,
                'image_id' => 1,
                'image_url' => 'http://example.com/image1.jpg',
                'title' => 'Slide 1',
                'blocks' => []
            ]
        ];

        Page::where('id', $page->id)->update([
            'gallery_slides' => json_encode($slides)
        ]);

        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('gallery', $data['data']['page']['page_type']);
        $this->assertNotNull($data['data']['page']['gallery_slides']);

        $duplicatedSlides = json_decode($data['data']['page']['gallery_slides'], true);
        $this->assertCount(1, $duplicatedSlides);
    }

    public function testCloneToSiteClonesGallerySlides()
    {
        $sourcePage = $this->createPage(['page_type' => 'gallery']);

        $slides = [
            [
                'id' => 0,
                'image_id' => 1,
                'image_url' => 'http://example.com/image1.jpg',
                'title' => 'Slide 1',
                'blocks' => []
            ]
        ];

        Page::where('id', $sourcePage->id)->update([
            'gallery_slides' => json_encode($slides)
        ]);

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($targetSiteId, $data['data']['page']['site_id']);
        $this->assertEquals('gallery', $data['data']['page']['page_type']);
        $this->assertNotNull($data['data']['page']['gallery_slides']);

        $clonedSlides = json_decode($data['data']['page']['gallery_slides'], true);
        $this->assertCount(1, $clonedSlides);
    }

    public function testStoreValidatesBlocks()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'Test Page'],
                'meta' => ['slug' => 'test-page', 'status' => 'draft']
            ],
            'blocks' => [
                [
                    'type' => 'text',
                    // Missing required 'paragraphs' field
                ]
            ]
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        // Should return validation error
        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
    }

    public function testStoreValidatesGallerySlideBlocks()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'Gallery Page'],
                'meta' => [
                    'slug' => 'gallery-page',
                    'status' => 'draft',
                    'content_type' => 'gallery'
                ]
            ],
            'gallery_slides' => [
                [
                    'id' => 0,
                    'image_id' => 1,
                    'image_url' => 'http://example.com/image1.jpg',
                    'title' => 'Slide 1',
                    'caption' => 'First slide',
                    'alt' => 'Alt text 1',
                    'blocks' => [
                        [
                            'type' => 'text',
                            // Missing required 'paragraphs' field
                        ]
                    ]
                ]
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        // Should return validation error
        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertStringContainsString('slide', strtolower(json_encode($data['errors'])));
    }

    public function testStoreAcceptsValidGallerySlideBlocks()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'Gallery Page'],
                'meta' => [
                    'slug' => 'gallery-page',
                    'status' => 'draft',
                    'content_type' => 'gallery'
                ]
            ],
            'gallery_slides' => [
                [
                    'id' => 0,
                    'image_id' => 1,
                    'image_url' => 'http://example.com/image1.jpg',
                    'title' => 'Slide 1',
                    'caption' => 'First slide',
                    'alt' => 'Alt text 1',
                    'blocks' => [
                        [
                            'type' => 'text',
                            'paragraphs' => ['This is valid text']
                        ]
                    ]
                ]
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Gallery Page', $data['data']['page']['title']);
    }

    public function testUpdateValidatesBlocks()
    {
        $page = $this->createPage();

        $updateData = [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'Updated Page'],
                'meta' => ['slug' => 'updated-page']
            ],
            'blocks' => [
                [
                    'type' => 'heading',
                    // Missing required 'text' field
                ]
            ]
        ];

        $response = $this->putForSite("/api/pages/{$page->id}", $updateData);

        // Should return validation error
        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
    }


    private function createSite() {
        return Site::create([
            'name' => 'Test Site 2',
            'slug' => 'test-site-2',
            'is_active' => true,
            'is_default' => false,
        ]);
    }

    public function testBulkDeletePages()
    {
        $page1 = $this->createPage();
        $page2 = $this->createPage();

        $response = $this->postForSite('/api/pages/bulk-delete', [
            'ids' => [$page1->id, $page2->id]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('message', $data['data']);
        $this->assertArrayHasKey('deleted', $data['data']);

        $this->assertNull(Page::find($page1->id));
        $this->assertNull(Page::find($page2->id));
    }

    public function testBulkDeleteReturns422WithoutIds()
    {
        $response = $this->postForSite('/api/pages/bulk-delete', []);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testBulkUpdateStatusPages()
    {
        $page1 = $this->createPage(['status' => 'draft']);
        $page2 = $this->createPage(['status' => 'draft']);

        $response = $this->postForSite('/api/pages/bulk-update-status', [
            'ids' => [$page1->id, $page2->id],
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('message', $data['data']);
        $this->assertArrayHasKey('updated', $data['data']);

        $updatedPage1 = Page::find($page1->id);
        $updatedPage2 = Page::find($page2->id);

        $this->assertEquals('published', $updatedPage1->status);
        $this->assertEquals('published', $updatedPage2->status);
    }

    public function testBulkUpdateStatusReturns422WithoutIds()
    {
        $response = $this->postForSite('/api/pages/bulk-update-status', [
            'status' => 'published'
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testBulkUpdateStatusReturns422WithoutStatus()
    {
        $page = $this->createPage();

        $response = $this->postForSite('/api/pages/bulk-update-status', [
            'ids' => [$page->id]
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreWithProducts()
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $pageData = [
            'site_id' => $this->siteId,
            'forms' => [
                'main' => ['title' => 'Page with Products'],
                'meta' => [
                    'slug' => 'page-with-products',
                    'status' => 'draft',
                ],
                'tags' => [
                    'products' => [$product1->id, $product2->id]
                ]
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']['page']['products']);
    }

    public function testDuplicatePageClonesProducts()
    {
        $product = $this->createProduct();
        $page = $this->createPage();

        $this->attachProductToPage($page, $product);

        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['data']['page']['products']);
    }

    public function testApprovePageSuccessfully()
    {
        $page = $this->createPage(['status' => 'waiting_approval', 'requires_approval' => true]);

        $response = $this->postForSite("/api/pages/{$page->id}/approve", [
            'user_id' => 1
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('published', $data['data']['page']['status']);
        $this->assertNotNull($data['data']['page']['approved_by']);
        $this->assertNotNull($data['data']['page']['approved_at']);
    }

    public function testApprovePageReturns400IfNotWaitingApproval()
    {
        $page = $this->createPage(['status' => 'draft']);

        $response = $this->postForSite("/api/pages/{$page->id}/approve", [
            'user_id' => 1
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRejectPageSuccessfully()
    {
        $page = $this->createPage(['status' => 'waiting_approval', 'requires_approval' => true]);

        $response = $this->postForSite("/api/pages/{$page->id}/reject", [
            'user_id' => 1,
            'reason' => 'Content needs revision'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('draft', $data['data']['page']['status']);
        $this->assertNull($data['data']['page']['approved_by']);
        $this->assertNull($data['data']['page']['approved_at']);
    }

    public function testPutPageOnHoldSuccessfully()
    {
        $page = $this->createPage(['status' => 'draft']);

        $response = $this->postForSite("/api/pages/{$page->id}/put-on-hold", [
            'user_id' => 1,
            'reason' => 'Waiting for legal review'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('on_hold', $data['data']['page']['status']);
    }

    public function testMakePagePrivateSuccessfully()
    {
        $page = $this->createPage(['status' => 'draft']);

        $response = $this->postForSite("/api/pages/{$page->id}/make-private", [
            'user_id' => 1
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('private', $data['data']['page']['status']);
    }

    public function testPublishPageWithApprovalRequiredGoesToWaitingApproval()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'requires_approval' => true,
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'published']
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Should be waiting_approval, not published
        $this->assertEquals('waiting_approval', $data['data']['page']['status']);
    }

    public function testPublishPageWithoutApprovalRequiredPublishes()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'requires_approval' => false,
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'published']
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('published', $data['data']['page']['status']);
    }

    public function testBulkApprovePages()
    {
        $page1 = Page::create([
            'title' => 'Test Page 1',
            'slug' => 'test-page-1',
            'status' => 'waiting_approval',
            'requires_approval' => true,
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Test Page 2',
            'slug' => 'test-page-2',
            'status' => 'waiting_approval',
            'requires_approval' => true,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite('/api/pages/bulk-approve', [
            'ids' => [$page1->id, $page2->id],
            'user_id' => 1
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('results', $data['data']);
        $this->assertTrue($data['data']['results'][$page1->id]['success']);
        $this->assertTrue($data['data']['results'][$page2->id]['success']);

        // Verify both pages are now published
        $updatedPage1 = Page::find($page1->id);
        $updatedPage2 = Page::find($page2->id);

        $this->assertEquals('published', $updatedPage1->status);
        $this->assertEquals('published', $updatedPage2->status);
        $this->assertNotNull($updatedPage1->approved_by);
        $this->assertNotNull($updatedPage2->approved_by);
    }

    public function testBulkApproveReturns422WithoutUserID()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'waiting_approval',
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite('/api/pages/bulk-approve', [
            'ids' => [$page->id]
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testBulkApproveReturns422WithoutIds()
    {
        $response = $this->postForSite('/api/pages/bulk-approve', [
            'user_id' => 1
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testFilterByWaitingApprovalStatus()
    {
        $this->createPage(['status' => 'draft']);
        Page::create([
            'title' => 'Waiting Page',
            'slug' => 'waiting-page',
            'status' => 'waiting_approval',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/pages?status=waiting_approval');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('waiting_approval', $data['items'][0]['status']);
    }

    public function testFilterByPrivateStatus()
    {
        $this->createPage(['status' => 'draft']);
        Page::create([
            'title' => 'Private Page',
            'slug' => 'private-page',
            'status' => 'private',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/pages?status=private');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('private', $data['items'][0]['status']);
    }

    public function testFilterByOnHoldStatus()
    {
        $this->createPage(['status' => 'draft']);
        Page::create([
            'title' => 'On Hold Page',
            'slug' => 'on-hold-page',
            'status' => 'on_hold',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/pages?status=on_hold');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('on_hold', $data['items'][0]['status']);
    }

    public function testApproveCreatesHistoryEntry()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'waiting_approval',
            'requires_approval' => true,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/pages/{$page->id}/approve", [
            'user_id' => 1
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify approval history entry
        $approvalHistory = PageHistory::where('page_id', $page->id)
            ->where('action', 'approved')
            ->first();

        $this->assertNotNull($approvalHistory);
        $this->assertEquals(1, $approvalHistory->changes['approved_by']);

        // Verify publish history entry
        $publishHistory = PageHistory::where('page_id', $page->id)
            ->where('action', 'published')
            ->first();

        $this->assertNotNull($publishHistory);
    }

    public function testRejectCreatesHistoryEntry()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'waiting_approval',
            'requires_approval' => true,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/pages/{$page->id}/reject", [
            'user_id' => 1,
            'reason' => 'Needs more work'
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify rejection history entry
        $history = PageHistory::where('page_id', $page->id)
            ->where('action', 'rejected')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals(1, $history->changes['rejected_by']);
        $this->assertEquals('Needs more work', $history->changes['reason']);
    }

    public function testOnHoldCreatesHistoryEntry()
    {
        $page = $this->createPage(['status' => 'draft']);

        $response = $this->postForSite("/api/pages/{$page->id}/put-on-hold", [
            'user_id' => 1,
            'reason' => 'Legal review needed'
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify on hold history entry
        $history = PageHistory::where('page_id', $page->id)
            ->where('action', 'on_hold')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals(1, $history->changes['user_id']);
        $this->assertEquals('Legal review needed', $history->changes['reason']);
    }

    public function testMakePrivateCreatesHistoryEntry()
    {
        $page = $this->createPage(['status' => 'draft']);

        $response = $this->postForSite("/api/pages/{$page->id}/make-private", [
            'user_id' => 1
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify private history entry
        $history = PageHistory::where('page_id', $page->id)
            ->where('action', 'made_private')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals(1, $history->changes['user_id']);
    }

    public function testCannotApproveAlreadyPublishedPage()
    {
        $page = $this->createPage(['status' => 'published']);

        $response = $this->postForSite("/api/pages/{$page->id}/approve", [
            'user_id' => 1
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('not waiting for approval', $data['error']);
    }

    public function testCannotRejectDraftPage()
    {
        $page = $this->createPage(['status' => 'draft']);

        $response = $this->postForSite("/api/pages/{$page->id}/reject", [
            'user_id' => 1
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUpdateFromWaitingApprovalToDraft()
    {
        $page = $this->createPage(['status' => 'waiting_approval']);

        $updateData = [
            'id' => $page->id,
            'status' => 'draft',
            'forms' => [
                'main' => ['title' => 'Test Page'],
                'meta' => ['slug' => 'test-page', 'status' => 'draft']
            ],
            'site_id' => $this->siteId
        ];

        $response = $this->putForSite("/api/pages/{$page->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('draft', $data['data']['page']['status']);
    }

    public function testCannotTransitionFromArchivedToPublished()
    {
        $page = $this->createPage(['status' => 'archived']);

        $updateData = [
            'id' => $page->id,
            'status' => 'published',
            'forms' => [
                'main' => ['title' => 'Test Page'],
                'meta' => ['slug' => 'test-page', 'status' => 'published']
            ],
            'site_id' => $this->siteId
        ];

        $response = $this->putForSite("/api/pages/{$page->id}", $updateData);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Cannot change status', $data['error']);
    }

    public function testDuplicatePageCopiesRequiresApprovalFlag()
    {
        $page = $this->createPage(['requires_approval' => true, 'status' => 'published']);;

        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['page']['requires_approval']);
        $this->assertEquals('draft', $data['data']['page']['status']);
    }

    public function testCloneToSiteCopiesRequiresApprovalFlag()
    {
        $page = $this->createPage(['requires_approval' => true, 'status' => 'published']);;

        $newSite = $this->createSite();

        $response = $this->postForSite("/api/pages/{$page->id}/clone-to-site", [
            'target_site_id' => $newSite->id
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['page']['requires_approval']);
        $this->assertEquals('draft', $data['data']['page']['status']);
    }

    public function testBulkApproveHandlesPartialFailures()
    {
        $waitingPage = $this->createPage(['status' => 'waiting_approval']);
        $draftPage = $this->createPage(['status' => 'draft']);

        $response = $this->postForSite('/api/pages/bulk-approve', [
            'ids' => [$waitingPage->id, $draftPage->id],
            'user_id' => 1
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Waiting page should succeed
        $this->assertTrue($data['data']['results'][$waitingPage->id]['success']);

        // Draft page should fail
        $this->assertFalse($data['data']['results'][$draftPage->id]['success']);
        $this->assertArrayHasKey('error', $data['data']['results'][$draftPage->id]);
    }

    public function testStoreWithRequiresApprovalAndPublishedStatusCreatesWaitingApprovalPage()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'requires_approval' => true,
            'status' => 'published',
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'published']
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('waiting_approval', $data['data']['page']['status']);
        $this->assertTrue($data['data']['page']['requires_approval']);

        // Verify history entries
        $pageId = $data['data']['page']['id'];

        $createdHistory = PageHistory::where('page_id', $pageId)
            ->where('action', 'created')
            ->first();
        $this->assertNotNull($createdHistory);

        $waitingHistory = PageHistory::where('page_id', $pageId)
            ->where('action', 'waiting_approval')
            ->first();
        $this->assertNotNull($waitingHistory);
    }

    public function testStoreWithRequiresApprovalAndDraftStatusCreatesDraftPage()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'requires_approval' => true,
            'status' => 'draft',
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'draft']
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('draft', $data['data']['page']['status']);
        $this->assertTrue($data['data']['page']['requires_approval']);
    }

    public function testStoreWithoutRequiresApprovalPublishesImmediately()
    {
        $pageData = [
            'site_id' => $this->siteId,
            'requires_approval' => false,
            'status' => 'published',
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'published']
            ],
            'blocks' => []
        ];

        $response = $this->postForSite('/api/pages', $pageData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('published', $data['data']['page']['status']);
        $this->assertFalse($data['data']['page']['requires_approval']);
    }

    public function testUpdateDraftWithRequiresApprovalToPublishedGoesToWaitingApproval()
    {
        $page = $this->createPage(['status' => 'draft', 'requires_approval' => true]);

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
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('waiting_approval', $data['data']['page']['status']);
    }

    public function testMakePageInternalSuccessfully()
    {
        $page = $this->createPage(['status' => 'draft']);

        $response = $this->postForSite("/api/pages/{$page->id}/make-internal", [
            'user_id' => 1
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('internal', $data['data']['page']['status']);
    }

    public function testFilterByInternalStatus()
    {
        $this->createPage(['status' => 'draft']);

        $this->createPage(['status' => 'internal']);

        $response = $this->getForSite('/api/pages?status=internal');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('internal', $data['items'][0]['status']);
    }

    public function testMakeInternalCreatesHistoryEntry()
    {
        $page = $this->createPage(['status' => 'draft']);

        $response = $this->postForSite("/api/pages/{$page->id}/make-internal", [
            'user_id' => 1
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify internal history entry
        $history = PageHistory::where('page_id', $page->id)
            ->where('action', 'made_internal')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals(1, $history->changes['user_id']);
    }

    public function testStoreCreatesNewPageWithCreator()
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
        $this->assertEquals(1, $data['data']['page']['created_by']);
        $this->assertEquals(1, $data['data']['page']['updated_by']);
    }

    public function testUpdateModifiesExistingPageWithUpdater()
    {
        $page = $this->createPage();

        $updateData = [
            'status' => 'published',
            'forms' => [
                'main' => ['title' => 'Updated Title'],
                'meta' => ['slug' => 'updated']
            ],
            'site_id' => $this->siteId
        ];

        $response = $this->putForSite("/api/pages/{$page->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Updated Title', $data['data']['page']['title']);
        $this->assertEquals(1, $data['data']['page']['created_by']); // Original creator
        $this->assertEquals(1, $data['data']['page']['updated_by']); // New updater
    }

    public function testDuplicatePageSetsNewCreator()
    {
        $page = $this->createPage();

        $response = $this->postForSite("/api/pages/{$page->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsString('Copy', $data['data']['page']['title']);
        $this->assertEquals('draft', $data['data']['page']['status']);

        // Duplicated page should have the current user as creator
        $this->assertEquals(1, $data['data']['page']['created_by']);
        $this->assertEquals(1, $data['data']['page']['updated_by']);
    }

    public function testCloneToSiteSetsNewCreator()
    {
        $sourcePage = $this->createPage();

        $newSite = $this->createSite();
        $targetSiteId = $newSite->id;

        $response = $this->postForSite("/api/pages/{$sourcePage->id}/clone-to-site", [
            'target_site_id' => $targetSiteId
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($targetSiteId, $data['data']['page']['site_id']);
        $this->assertEquals('draft', $data['data']['page']['status']);

        // Cloned page should have the current user as creator
        $this->assertEquals(1, $data['data']['page']['created_by']);
        $this->assertEquals(1, $data['data']['page']['updated_by']);
    }
}