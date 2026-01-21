<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Block;
use App\Models\Category;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCustomField;
use App\Models\PageMetadata;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
use App\Models\Tag;
use App\Repositories\Cms\PageRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use DateTime;

class PageRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PageRepository();
    }

    public function test_it_can_find_page_by_slug(): void
    {
        // Arrange
        $page = $this->createPage(['slug' => 'unique-test-slug']);

        // Act
        $found = $this->repository->findBySlug('unique-test-slug');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($page->id, $found->id);
        $this->assertEquals('unique-test-slug', $found->slug);
    }

    public function test_it_returns_null_when_slug_not_found(): void
    {
        // Act
        $found = $this->repository->findBySlug('non-existent-slug');

        // Assert
        $this->assertNull($found);
    }

    public function test_it_filters_pages_by_site(): void
    {
        // Arrange
        $this->createPage(['slug' => 'site-1-page', 'site_id' => $this->siteId]);

        // Act
        $found = $this->repository->findBySlug('site-1-page');

        // Assert - should not find page from different site
        $this->assertNotNull($found);
    }

    public function test_it_returns_published_pages_only(): void
    {
        // Arrange
        $this->createPage(['status' => 'published', 'title' => 'Published 1']);
        $this->createPage(['status' => 'published', 'title' => 'Published 2']);
        $this->createPage(['status' => 'draft', 'title' => 'Draft']);

        // Act
        $pages = $this->repository->getPublishedPages();

        // Assert
        $this->assertCount(2, $pages);
        foreach ($pages as $page) {
            $this->assertEquals('published', $page->status);
        }
    }

    public function test_it_orders_published_pages_by_created_at_desc(): void
    {
        // Arrange
        $oldest = $this->createPage([
            'status' => 'published',
            'created_at' => '2024-01-01 00:00:00'
        ]);
        $newest = $this->createPage([
            'status' => 'published',
            'created_at' => '2024-12-31 23:59:59'
        ]);

        // Act
        $pages = $this->repository->getPublishedPages();
        $pages = $pages->toArray();

        // Assert
        $this->assertEquals($oldest->id, $pages[0]['id']);
        $this->assertEquals($newest->id, $pages[1]['id']);
    }

    public function test_search_loads_all_relationships(): void
    {
        // Arrange
        $page = $this->createPage();
        $block = $this->createBlock($page->id);
        $category = $this->createCategory();
        $this->attachCategoryToPage($page, $category);
        $tag = $this->createTag();
        $this->attachTagToPage($page, $tag);
        $this->createPageMetadata($page->id, ['featured' => 1]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertGreaterThan(0, count($result->getData()));
        $foundPage = $result->getData()[0];

        $this->assertNotEmpty($foundPage['blocks']);
        $this->assertNotEmpty($foundPage['categories']);
        $this->assertNotEmpty($foundPage['tags']);
        $this->assertNotEmpty($foundPage['metadata']);

        $this->assertCount(1, $foundPage['blocks']);
        $this->assertCount(1, $foundPage['categories']);
        $this->assertCount(1, $foundPage['tags']);
    }

    public function test_quick_search_returns_collection(): void
    {
        // Arrange
        $this->createPages(3);

        // Act
        $result = $this->repository->quickSearch('', ['limit' => 10]);

        // Assert
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
        $this->assertGreaterThanOrEqual(3, $result->count());
    }

    public function test_quick_search_filters_by_status(): void
    {
        // Arrange
        $this->createPage(['status' => 'published', 'title' => 'Published']);
        $this->createPage(['status' => 'draft', 'title' => 'Draft']);
        $this->createPage(['status' => 'archived', 'title' => 'Archived']);

        // Act
        $result = $this->repository->quickSearch('', ['status' => 'draft']);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Draft', $result->first()['title']);
    }

    public function test_quick_search_filters_by_site_id(): void
    {
        // Arrange
        $this->createPage(['site_id' => $this->siteId, 'title' => 'Site 1']);

        // Act
        $result = $this->repository->quickSearch('', ['site_id' => $this->siteId]);

        // Assert
        $this->assertGreaterThanOrEqual(1, $result->count());
        foreach ($result as $page) {
            $this->assertEquals($this->siteId, $page['site_id']);
        }
    }

    public function test_quick_search_respects_limit(): void
    {
        // Arrange
        $this->createPages(10);

        // Act
        $result = $this->repository->quickSearch('', ['limit' => 3]);

        // Assert
        $this->assertLessThanOrEqual(3, $result->count());
    }

    public function test_quick_search_loads_specified_relationships(): void
    {
        // Arrange
        $page = $this->createPage();
        $this->createBlock($page->id);
        $category = $this->createCategory();
        $this->attachCategoryToPage($page, $category);

        // Act
        $result = $this->repository->quickSearch('', [
            'with' => ['blocks', 'categories'],
            'limit' => 10
        ]);

        // Assert
        $foundPage = $result->first();
        $this->assertNotEmpty($foundPage['blocks']);
        $this->assertNotEmpty($foundPage['categories']);
    }

    public function test_get_pages_by_category_returns_correct_pages(): void
    {
        // Arrange
        $category = $this->createCategory();
        $page1 = $this->createPage(['title' => 'Page 1']);
        $page2 = $this->createPage(['title' => 'Page 2']);
        $page3 = $this->createPage(['title' => 'Page 3']);

        $this->attachCategoryToPage($page1, $category);
        $this->attachCategoryToPage($page2, $category);

        // Act
        $pages = $this->repository->getPagesByCategory($category->id, null, $this->siteId);;

        // Assert
        $this->assertCount(2, $pages);
        $this->assertCollectionContains($pages, ['title' => 'Page 1']);
        $this->assertCollectionContains($pages, ['title' => 'Page 2']);
        $this->assertCollectionDoesNotContain($pages, ['title' => 'Page 3']);
    }

    public function test_get_pages_by_category_respects_limit(): void
    {
        // Arrange
        $category = $this->createCategory();
        $pages = $this->createPages(5);

        echo $category->id;

        foreach ($pages as $page) {
            $this->attachCategoryToPage($page, $category);
        }

        // Act
        $result = $this->repository->getPagesByCategory($category->id, 2, $this->siteId);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_get_pages_by_category_filters_by_site(): void
    {
        // Arrange
        $category = $this->createCategory();
        $page1 = $this->createPage(['site_id' => $this->siteId]);

        $this->attachCategoryToPage($page1, $category);

        // Act
        $pages = $this->repository->getPagesByCategory($category->id, null, $this->siteId);;

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($page1->id, $pages->first()->id);
    }

    public function test_get_featured_pages_returns_only_featured(): void
    {
        // Arrange
        $page1 = $this->createPage(['status' => 'published', 'title' => 'Featured 1']);
        $page2 = $this->createPage(['status' => 'published', 'title' => 'Featured 2']);
        $page3 = $this->createPage(['status' => 'published', 'title' => 'Not Featured']);

        $this->createPageMetadata($page1->id, ['featured' => 1]);
        $this->createPageMetadata($page2->id, ['featured' => 1]);
        $this->createPageMetadata($page3->id, ['featured' => 0]);

        // Act
        $pages = $this->repository->getFeaturedPages(null, $this->siteId);

        // Assert
        $this->assertCount(2, $pages);
        $this->assertCollectionContains($pages, ['title' => 'Featured 1']);
        $this->assertCollectionContains($pages, ['title' => 'Featured 2']);
    }

    public function test_get_featured_pages_filters_by_site_and_status(): void
    {
        // Arrange
        $publishedFeatured = $this->createPage([
            'site_id' => $this->siteId,
            'status' => 'published'
        ]);
        $draftFeatured = $this->createPage([
            'site_id' => $this->siteId,
            'status' => 'draft'
        ]);

        $site = $this->createSite();

        $otherSiteFeatured = $this->createPage([
            'site_id' => $site->id,
            'status' => 'published'
        ]);

        $this->createPageMetadata($publishedFeatured->id, ['featured' => 1]);
        $this->createPageMetadata($draftFeatured->id, ['featured' => 1]);
        $this->createPageMetadata($otherSiteFeatured->id, ['featured' => 1]);

        // Act
        $pages = $this->repository->getFeaturedPages(null, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($publishedFeatured->id, $pages->first()->id);
    }

    public function test_get_featured_pages_respects_limit(): void
    {
        // Arrange
        $pages = $this->createPages(5, ['status' => 'published']);
        foreach ($pages as $page) {
            $this->createPageMetadata($page->id, ['featured' => 1]);
        }

        // Act
        $result = $this->repository->getFeaturedPages(3, $this->siteId);

        // Assert
        $this->assertCount(3, $result);
    }

    public function test_get_complete_page_data_loads_all_relationships(): void
    {
        // Arrange
        $page = $this->createPage();
        $block = $this->createBlock($page->id);
        $category = $this->createCategory();
        $this->attachCategoryToPage($page, $category);
        $tag = $this->createTag();
        $this->attachTagToPage($page, $tag);
        $this->createPageMetadata($page->id);
        $this->createPageSeo($page->id);
        $this->createPageSettings($page->id);
        $this->createPageSocial($page->id);
        $author = $this->createAuthor();
        $this->attachAuthorToPage($page, $author);
        $customField = $this->createCustomFieldDefinition();
        $this->createPageCustomField($page->id, $customField);

        // Act
        $result = $this->repository->getCompletePageData($page->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertRelationLoaded($result, 'blocks');
        $this->assertRelationLoaded($result, 'categories');
        $this->assertRelationLoaded($result, 'tags');
        $this->assertRelationLoaded($result, 'metadata');
        $this->assertRelationLoaded($result, 'seo');
        $this->assertRelationLoaded($result, 'settings');
        $this->assertRelationLoaded($result, 'social');
        $this->assertRelationLoaded($result, 'customFields');
        $this->assertRelationLoaded($result, 'authors');
    }

    public function test_duplicate_blocks_creates_exact_copies(): void
    {
        // Arrange
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        $block1 = $this->createBlock($sourcePage->id, [
            'type' => 'text',
            'data' => json_encode(['content' => 'Text content']),
            'order' => 0
        ]);
        $block2 = $this->createBlock($sourcePage->id, [
            'type' => 'image',
            'data' => json_encode(['url' => 'image.jpg']),
            'order' => 1
        ]);

        // Act
        $this->repository->duplicateBlocks($sourcePage->id, $targetPage->id);

        // Assert
        $this->assertDatabaseHas('blocks', [
            'page_id' => $targetPage->id,
            'type' => 'text',
            'order' => 0
        ]);
        $this->assertDatabaseHas('blocks', [
            'page_id' => $targetPage->id,
            'type' => 'image',
            'order' => 1
        ]);

        $targetBlocks = Block::where('page_id', $targetPage->id)
            ->orderBy('order')
            ->get();

        $targetBlocks = $targetBlocks->toArray();

        $this->assertCount(2, $targetBlocks);
        $this->assertEquals('text', $targetBlocks[0]['type']);
        $this->assertEquals('image', $targetBlocks[1]['type']);
    }

    public function test_duplicate_metadata_copies_all_fields(): void
    {
        // Arrange
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        $this->createPageMetadata($sourcePage->id, [
            'featured' => 1,
            'excerpt' => 'Test excerpt'
        ]);

        // Act
        $this->repository->duplicateMetadata($sourcePage->id, $targetPage->id);

        // Assert
        $metadata = PageMetadata::where('page_id', $targetPage->id)->first();
        $this->assertNotNull($metadata);
        $this->assertEquals(1, $metadata->featured);
    }

    public function test_duplicate_seo_copies_all_fields(): void
    {
        // Arrange
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        $this->createPageSeo($sourcePage->id, [
            'meta_title' => 'SEO Title',
            'meta_description' => 'SEO Description'
        ]);

        // Act
        $this->repository->duplicateSeo($sourcePage->id, $targetPage->id);

        // Assert
        $seo = PageSeo::where('page_id', $targetPage->id)->first();
        $this->assertNotNull($seo);
        $this->assertEquals('SEO Title', $seo->meta_title);
        $this->assertEquals('SEO Description', $seo->meta_description);
    }

    public function test_duplicate_settings_copies_all_fields(): void
    {
        // Arrange
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        $this->createPageSettings($sourcePage->id);

        // Act
        $this->repository->duplicateSettings($sourcePage->id, $targetPage->id);

        // Assert
        $settings = PageSettings::where('page_id', $targetPage->id)->first();
        $this->assertNotNull($settings);
    }

    public function test_duplicate_social_copies_all_fields(): void
    {
        // Arrange
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        $this->createPageSocial($sourcePage->id);

        // Act
        $this->repository->duplicateSocial($sourcePage->id, $targetPage->id);

        // Assert
        $social = PageSocial::where('page_id', $targetPage->id)->first();
        $this->assertNotNull($social);
    }

    public function test_duplicate_categories_creates_associations(): void
    {
        // Arrange
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        $category1 = $this->createCategory();
        $category2 = $this->createCategory();

        $this->attachCategoryToPage($sourcePage, $category1);
        $this->attachCategoryToPage($sourcePage, $category2);

        // Act
        $this->repository->duplicateCategories($sourcePage->id, $targetPage->id);

        // Assert
        $this->assertDatabaseHas('page_categories', [
            'page_id' => $targetPage->id,
            'category_id' => $category1->id
        ]);
        $this->assertDatabaseHas('page_categories', [
            'page_id' => $targetPage->id,
            'category_id' => $category2->id
        ]);

        $count = $this->countRecords('page_categories', ['page_id' => $targetPage->id]);
        $this->assertEquals(2, $count);
    }

    public function test_duplicate_tags_creates_associations(): void
    {
        // Arrange
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        $tag1 = $this->createTag();
        $tag2 = $this->createTag();

        $this->attachTagToPage($sourcePage, $tag1);
        $this->attachTagToPage($sourcePage, $tag2);

        // Act
        $this->repository->duplicateTags($sourcePage->id, $targetPage->id);

        // Assert
        $count = $this->countRecords('page_tags', ['page_id' => $targetPage->id]);
        $this->assertEquals(2, $count);
    }

    public function test_duplicate_custom_fields_copies_values(): void
    {
        // Arrange
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        $field1 = $this->createCustomFieldDefinition(['key' => 'field1']);
        $field2 = $this->createCustomFieldDefinition(['key' => 'field2']);

        $this->createPageCustomField($sourcePage->id, $field1, ['value' => 'Value 1']);
        $this->createPageCustomField($sourcePage->id, $field2, ['value' => 'Value 2']);

        // Act
        $this->repository->duplicateCustomFields($sourcePage->id, $targetPage->id);

        // Assert
        $customFields = PageCustomField::where('page_id', $targetPage->id)->get();
        $this->assertCount(2, $customFields);
    }

    public function test_duplicate_page_authors_maintains_order(): void
    {
        // Arrange
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        $author1 = $this->createAuthor(['name' => 'Author 1']);
        $author2 = $this->createAuthor(['name' => 'Author 2']);

        $this->attachAuthorToPage($sourcePage, $author1, ['sort_order' => 0, 'role' => 'primary']);
        $this->attachAuthorToPage($sourcePage, $author2, ['sort_order' => 1, 'role' => 'contributor']);

        // Act
        $this->repository->duplicatePageAuthors($sourcePage->id, $targetPage->id);

        // Assert
        $pageAuthors = PageAuthor::where('page_id', $targetPage->id)
            ->orderBy('sort_order')
            ->get();

        $pageAuthors = $pageAuthors->toArray();

        $this->assertCount(2, $pageAuthors);
        $this->assertEquals($author1->id, $pageAuthors[0]['author_id']);;
        $this->assertEquals('primary', $pageAuthors[0]['role']);
        $this->assertEquals($author2->id, $pageAuthors[1]['author_id']);;
        $this->assertEquals('contributor', $pageAuthors[1]['role']);
    }

    public function test_slug_exists_in_site_returns_true_when_exists(): void
    {
        // Arrange
        $this->createPage(['slug' => 'existing-slug', 'site_id' => $this->siteId]);

        // Act
        $exists = $this->repository->slugExistsInSite('existing-slug', $this->siteId);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_slug_exists_in_site_returns_false_when_not_exists(): void
    {
        // Act
        $exists = $this->repository->slugExistsInSite('non-existent-slug', $this->siteId);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_slug_exists_in_site_only_checks_specific_site(): void
    {
        $site = $this->createSite();
        // Arrange
        $this->createPage(['slug' => 'test-slug', 'site_id' => $site->id]);

        // Act
        $exists = $this->repository->slugExistsInSite('test-slug', $this->siteId);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_duplicate_categories_to_site_creates_category_if_not_exists(): void
    {
        // Arrange
        $sourcePage = $this->createPage(['site_id' => $this->siteId]);
        $targetSite = $this->createSite();
        $targetPage = Page::create([
            'site_id' => $targetSite->id,
            'slug' => 'target-page',
            'title' => 'Target Page',
            'status' => 'published',
        ]);

        $category = $this->createCategory(['slug' => 'test-category', 'name' => 'Test Category']);
        $this->attachCategoryToPage($sourcePage, $category);

        // Act
        $this->repository->duplicateCategoriesToSite($sourcePage->id, $targetPage->id, $targetSite->id);

        // Assert
        $targetCategory = Category::where('slug', 'test-category')
            ->where('site_id', $targetSite->id)
            ->first();

        $this->assertNotNull($targetCategory);
        $this->assertEquals('Test Category', $targetCategory->name);

        $this->assertDatabaseHas('page_categories', [
            'page_id' => $targetPage->id,
            'category_id' => $targetCategory->id,
        ]);
    }

    public function test_duplicate_categories_to_site_uses_existing_category(): void
    {
        // Arrange
        $sourcePage = $this->createPage(['site_id' => $this->siteId]);
        $targetSite = $this->createSite();
        $targetPage = Page::create([
            'site_id' => $targetSite->id,
            'slug' => 'target-page',
            'title' => 'Target Page',
            'status' => 'published',
        ]);

        // Create category in both sites with same slug
        $sourceCategory = $this->createCategory(['slug' => 'shared-category']);
        $existingCategory = Category::create([
            'site_id' => $targetSite->id,
            'slug' => 'shared-category',
            'name' => 'Existing Category',
        ]);

        $this->attachCategoryToPage($sourcePage, $sourceCategory);

        $initialCount = Category::where('site_id', $targetSite->id)->count();

        // Act
        $this->repository->duplicateCategoriesToSite($sourcePage->id, $targetPage->id, $targetSite->id);

        // Assert - should not create duplicate category
        $finalCount = Category::where('site_id', $targetSite->id)->count();
        $this->assertEquals($initialCount, $finalCount);

        $this->assertDatabaseHas('page_categories', [
            'page_id' => $targetPage->id,
            'category_id' => $existingCategory->id
        ]);
    }

    public function test_duplicate_tags_to_site_creates_tag_if_not_exists(): void
    {
        // Arrange
        $sourcePage = $this->createPage(['site_id' => $this->siteId]);
        $targetSite = $this->createSite();
        $targetPage = Page::create([
            'site_id' => $targetSite->id,
            'slug' => 'target-page',
            'title' => 'Target Page',
            'status' => 'published',
        ]);

        $tag = $this->createTag(['slug' => 'test-tag', 'name' => 'Test Tag']);
        $this->attachTagToPage($sourcePage, $tag);

        // Act
        $this->repository->duplicateTagsToSite($sourcePage->id, $targetPage->id, $targetSite->id);

        // Assert
        $targetTag = Tag::where('slug', 'test-tag')
            ->where('site_id', $targetSite->id)
            ->first();

        $this->assertNotNull($targetTag);
        $this->assertEquals('Test Tag', $targetTag->name);
    }

    public function test_duplicate_custom_fields_to_site_creates_definition_if_not_exists(): void
    {
        // Arrange
        $sourcePage = $this->createPage(['site_id' => $this->siteId]);
        $targetSite = $this->createSite();
        $targetPage = Page::create([
            'site_id' => $targetSite->id,
            'slug' => 'target-page',
            'title' => 'Target Page',
            'status' => 'published',
        ]);

        $definition = $this->createCustomFieldDefinition([
            'key' => 'custom_field',
            'name' => 'Custom Field'
        ]);
        $this->createPageCustomField($sourcePage->id, $definition, ['field_value' => 'Test Value']);

        // Act
        $this->repository->duplicateCustomFieldsToSite($sourcePage->id, $targetPage->id, $targetSite->id);

        // Assert
        $targetDefinition = CustomFieldDefinition::where('key', 'custom_field')
            ->where('site_id', $targetSite->id)
            ->first();

        $this->assertNotNull($targetDefinition);
        $this->assertStringContainsString('copy', strtolower($targetDefinition->name));

        $customField = PageCustomField::where('page_id', $targetPage->id)
            ->where('custom_field_definition_id', $targetDefinition->id)
            ->first();

        $this->assertNotNull($customField);
        $this->assertEquals('Test Value', $customField->field_value);
    }

    public function test_sync_tags_removes_existing_and_adds_new(): void
    {
        // Arrange
        $page = $this->createPage();
        $oldTag = $this->createTag();
        $newTag = $this->createTag();

        $this->attachTagToPage($page, $oldTag);

        // Act
        $result = $this->repository->syncTags($page->id, $newTag->id);

        // Assert
        $this->assertDatabaseMissing('page_tags', [
            'page_id' => $page->id,
            'tag_id' => $oldTag->id
        ]);

        $this->assertDatabaseHas('page_tags', [
            'page_id' => $page->id,
            'tag_id' => $newTag->id
        ]);

        $count = $this->countRecords('page_tags', ['page_id' => $page->id]);
        $this->assertEquals(1, $count);
    }

    public function test_duplicate_metadata_casts_datetime_fields(): void
    {
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        $publishDate = new DateTime('2024-12-25 10:00:00');

        PageMetadata::create([
            'page_id' => $sourcePage->id,
            'publish_date' => $publishDate->format('Y-m-d H:i:s'),
            'featured' => 1
        ]);

        $this->repository->duplicateMetadata($sourcePage->id, $targetPage->id);

        $metadata = PageMetadata::where('page_id', $targetPage->id)->first();

        $this->assertNotNull($metadata);
        $this->assertInstanceOf(DateTime::class, $metadata->publish_date);
        $this->assertEquals('2024-12-25 10:00:00', $metadata->publish_date->format('Y-m-d H:i:s'));
    }

    public function test_duplicate_settings_casts_numeric_fields(): void
    {
        $sourcePage = $this->createPage();
        $targetPage = $this->createPage();

        PageSettings::create([
            'page_id' => $sourcePage->id,
            'menu_order' => '5',
            'latitude' => '51.5074',
            'price' => '99.99',
            'recurring' => '1'
        ]);

        $this->repository->duplicateSettings($sourcePage->id, $targetPage->id);

        $settings = PageSettings::where('page_id', $targetPage->id)->first();

        $this->assertNotNull($settings);
        $this->assertIsInt($settings->menu_order);
        $this->assertEquals(5, $settings->menu_order);
        $this->assertIsFloat($settings->latitude);
        $this->assertEquals(51.5074, $settings->latitude);
    }

    public function test_search_calendar_pages_returns_published_pages_in_date_range(): void
    {
        // Arrange
        $pageInRange = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00'
        ]);

        $pageOutOfRange = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-02-15 10:00:00'
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);;

        // Assert
        $this->assertGreaterThan(0, count($result->getData()));
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($pageInRange->id, $pageIds);
        $this->assertNotContains($pageOutOfRange->id, $pageIds);
    }

    public function test_search_calendar_pages_returns_scheduled_pages_in_date_range(): void
    {
        // Arrange
        $pageInRange = $this->createPage([
            'status' => 'scheduled',
            'scheduled_at' => '2025-01-15 10:00:00'
        ]);

        $pageOutOfRange = $this->createPage([
            'status' => 'scheduled',
            'scheduled_at' => '2025-02-15 10:00:00'
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);

        // Assert
        $this->assertGreaterThan(0, count($result->getData()));
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($pageInRange->id, $pageIds);
        $this->assertNotContains($pageOutOfRange->id, $pageIds);
    }

    public function test_search_calendar_pages_filters_by_status(): void
    {
        // Arrange
        $publishedPage = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00'
        ]);

        $scheduledPage = $this->createPage([
            'status' => 'scheduled',
            'scheduled_at' => '2025-01-16 10:00:00'
        ]);

        // Act - only published
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status', 'published');
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);;

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($publishedPage->id, $pageIds);
        $this->assertNotContains($scheduledPage->id, $pageIds);
    }

    public function test_search_calendar_pages_filters_by_status_in(): void
    {
        // Arrange
        $publishedPage = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00'
        ]);

        $scheduledPage = $this->createPage([
            'status' => 'scheduled',
            'scheduled_at' => '2025-01-16 10:00:00'
        ]);

        $draftPage = $this->createPage([
            'status' => 'draft'
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);;;

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($publishedPage->id, $pageIds);
        $this->assertContains($scheduledPage->id, $pageIds);
        $this->assertNotContains($draftPage->id, $pageIds);
    }

    public function test_search_calendar_pages_filters_by_author_ids(): void
    {
        // Arrange
        $author1 = $this->createAuthor(['name' => 'Author 1']);
        $author2 = $this->createAuthor(['name' => 'Author 2']);

        $page1 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00'
        ]);

        $page2 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-16 10:00:00'
        ]);

        $this->attachAuthorToPage($page1, $author1);
        $this->attachAuthorToPage($page2, $author2);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->addFilter('author_ids', [$author1->id]);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($page1->id, $pageIds);
        $this->assertNotContains($page2->id, $pageIds);
    }

    public function test_search_calendar_pages_filters_by_multiple_author_ids(): void
    {
        // Arrange
        $author1 = $this->createAuthor(['name' => 'Author 1']);
        $author2 = $this->createAuthor(['name' => 'Author 2']);
        $author3 = $this->createAuthor(['name' => 'Author 3']);

        $page1 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00'
        ]);

        $page2 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-16 10:00:00'
        ]);

        $page3 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-17 10:00:00'
        ]);

        $this->attachAuthorToPage($page1, $author1);
        $this->attachAuthorToPage($page2, $author2);
        $this->attachAuthorToPage($page3, $author3);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->addFilter('author_ids', [$author1->id, $author2->id]);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);;

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($page1->id, $pageIds);
        $this->assertContains($page2->id, $pageIds);
        $this->assertNotContains($page3->id, $pageIds);
    }

    public function test_search_calendar_pages_filters_by_page_types(): void
    {
        // Arrange
        $blogPage = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00',
            'page_type' => 'blog'
        ]);

        $eventPage = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-16 10:00:00',
            'page_type' => 'event'
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->addFilter('page_types', ['blog']);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);;;

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($blogPage->id, $pageIds);
        $this->assertNotContains($eventPage->id, $pageIds);
    }

    public function test_search_calendar_pages_filters_by_multiple_page_types(): void
    {
        // Arrange
        $blogPage = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00',
            'page_type' => 'blog'
        ]);

        $eventPage = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-16 10:00:00',
            'page_type' => 'event'
        ]);

        $articlePage = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-17 10:00:00',
            'page_type' => 'article'
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->addFilter('page_types', ['blog', 'event']);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($blogPage->id, $pageIds);
        $this->assertContains($eventPage->id, $pageIds);
        $this->assertNotContains($articlePage->id, $pageIds);
    }

    public function test_search_calendar_pages_filters_by_tag_ids(): void
    {
        // Arrange
        $tag1 = $this->createTag(['name' => 'Tech']);
        $tag2 = $this->createTag(['name' => 'News']);

        $page1 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00'
        ]);

        $page2 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-16 10:00:00'
        ]);

        $this->attachTagToPage($page1, $tag1);
        $this->attachTagToPage($page2, $tag2);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->addFilter('tag_ids', [$tag1->id]);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($page1->id, $pageIds);
        $this->assertNotContains($page2->id, $pageIds);
    }

    public function test_search_calendar_pages_filters_by_site_ids(): void
    {
        // Arrange
        $site2 = $this->createSite();

        $page1 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00',
            'site_id' => $this->siteId
        ]);

        $page2 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-16 10:00:00',
            'site_id' => $site2->id
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->addFilter('site_ids', [$this->siteId]);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria);

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($page1->id, $pageIds);
        $this->assertNotContains($page2->id, $pageIds);
    }

    public function test_search_calendar_pages_applies_default_site_filter_when_no_site_ids(): void
    {
        // Arrange
        $site2 = $this->createSite();

        $page1 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00',
            'site_id' => $this->siteId
        ]);

        $page2 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-16 10:00:00',
            'site_id' => $site2->id
        ]);

        // Act - no site_ids filter, should use default site filter
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($page1->id, $pageIds);
        $this->assertNotContains($page2->id, $pageIds);
    }

    public function test_search_calendar_pages_orders_by_date_ascending(): void
    {
        // Arrange
        $page1 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-20 10:00:00',
            'title' => 'Latest'
        ]);

        $page2 = $this->createPage([
            'status' => 'scheduled',
            'scheduled_at' => '2025-01-15 10:00:00',
            'title' => 'Middle'
        ]);

        $page3 = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-10 10:00:00',
            'title' => 'Earliest'
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);;

        // Assert
        $data = $result->getData();
        $this->assertGreaterThanOrEqual(3, count($data));

        $pageIds = array_column($data, 'id');
        $page3Index = array_search($page3->id, $pageIds);
        $page2Index = array_search($page2->id, $pageIds);
        $page1Index = array_search($page1->id, $pageIds);

        $this->assertLessThan($page2Index, $page3Index);
        $this->assertLessThan($page1Index, $page2Index);
    }

    public function test_search_calendar_pages_excludes_draft_pages(): void
    {
        // Arrange
        $publishedPage = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00'
        ]);

        $draftPage = $this->createPage([
            'status' => 'draft'
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);;;

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($publishedPage->id, $pageIds);
        $this->assertNotContains($draftPage->id, $pageIds);
    }

//    public function test_search_calendar_pages_loads_author_relationships(): void
//    {
//        // Arrange
//        $author = $this->createAuthor(['name' => 'Test Author']);
//
//        $page = $this->createPage([
//            'status' => 'published',
//            'published_at' => '2025-01-15 10:00:00'
//        ]);
//
//        $this->attachAuthorToPage($page, $author);
//
//        // Act
//        $criteria = new SearchCriteria();
//        $criteria->addFilter('date_range', [
//            'start' => '2025-01-01 00:00:00',
//            'end' => '2025-01-31 23:59:59'
//        ]);
//        $criteria->addFilter('status_in', ['scheduled', 'published']);
//        $criteria->setPerPage(100);
//
//        $result = $this->repository->searchCalendarPages($criteria);
//
//        // Assert
//        $data = $result->getData();
//
//        echo '<pre>';
//        print_r($data);
//        die;
//
//        $foundPage = collect($data)->where('id', $page->id)->first();
//
//        $this->assertNotNull($foundPage);
//        $this->assertNotEmpty($foundPage['author']);
//        $this->assertEquals('Test Author', $foundPage['author']['name']);
//    }

    public function test_search_calendar_pages_combines_multiple_filters(): void
    {
        // Arrange
        $author = $this->createAuthor(['name' => 'Test Author']);
        $tag = $this->createTag(['name' => 'Tech']);

        $matchingPage = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00',
            'page_type' => 'blog'
        ]);

        $nonMatchingPage = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-16 10:00:00',
            'page_type' => 'article'
        ]);

        $this->attachAuthorToPage($matchingPage, $author);
        $this->attachTagToPage($matchingPage, $tag);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->addFilter('author_ids', [$author->id]);
        $criteria->addFilter('page_types', ['blog']);
        $criteria->addFilter('tag_ids', [$tag->id]);
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);;;

        // Assert
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($matchingPage->id, $pageIds);
        $this->assertNotContains($nonMatchingPage->id, $pageIds);
    }

    public function test_search_calendar_pages_handles_empty_filters(): void
    {
        // Arrange
        $page = $this->createPage([
            'status' => 'published',
            'published_at' => '2025-01-15 10:00:00'
        ]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->addFilter('author_ids', []); // Empty array
        $criteria->addFilter('page_types', []); // Empty array
        $criteria->addFilter('tag_ids', []); // Empty array
        $criteria->setPerPage(100);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);;;;

        // Assert - should ignore empty filters and return all pages
        $pageIds = array_column($result->getData(), 'id');
        $this->assertContains($page->id, $pageIds);
    }

    public function test_search_calendar_pages_respects_per_page_limit(): void
    {
        // Arrange
        for ($i = 0; $i < 15; $i++) {
            $this->createPage([
                'status' => 'published',
                'published_at' => '2025-01-' . str_pad($i + 1, 2, '0', STR_PAD_LEFT) . ' 10:00:00',
                'slug' => 'page-' . $i
            ]);
        }

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('date_range', [
            'start' => '2025-01-01 00:00:00',
            'end' => '2025-01-31 23:59:59'
        ]);
        $criteria->addFilter('status_in', ['scheduled', 'published']);
        $criteria->setPerPage(10);

        $result = $this->repository->searchCalendarPages($criteria, $this->siteId);;;;;

        // Assert
        $this->assertLessThanOrEqual(10, count($result->getData()));
        $this->assertGreaterThanOrEqual(15, $result->getTotal());
    }

    public function test_search_pipeline_returns_all_stages(): void
    {
        // Arrange
        $this->createPage(['status' => 'draft', 'title' => 'Draft Page']);
        $this->createPage(['status' => 'waiting_approval', 'title' => 'Review Page']);
        $this->createPage(['status' => 'scheduled', 'title' => 'Scheduled Page']);
        $this->createPage(['status' => 'published', 'title' => 'Published Page']);

        // Act
        $criteria = new SearchCriteria();
        $results = $this->repository->searchPipeline($criteria);

        // Assert
        $this->assertArrayHasKey('draft', $results);
        $this->assertArrayHasKey('waiting_approval', $results);
        $this->assertArrayHasKey('scheduled', $results);
        $this->assertArrayHasKey('published', $results);
    }

    public function test_search_pipeline_groups_pages_by_status(): void
    {
        // Arrange
        $this->createPage(['status' => 'draft', 'title' => 'Draft 1']);
        $this->createPage(['status' => 'draft', 'title' => 'Draft 2']);
        $this->createPage(['status' => 'waiting_approval', 'title' => 'Review 1']);

        // Act
        $criteria = new SearchCriteria();
        $results = $this->repository->searchPipeline($criteria);

        // Assert
        $this->assertCount(2, $results['draft']['cards']);
        $this->assertCount(1, $results['waiting_approval']['cards']);
        $this->assertEquals(2, $results['draft']['total']);
        $this->assertEquals(1, $results['waiting_approval']['total']);
    }

    public function test_search_pipeline_includes_stage_limits(): void
    {
        // Act
        $criteria = new SearchCriteria();
        $results = $this->repository->searchPipeline($criteria);

        // Assert
        $this->assertEquals(10, $results['draft']['limit']);
        $this->assertEquals(5, $results['waiting_approval']['limit']);
        $this->assertNull($results['scheduled']['limit']);
        $this->assertNull($results['published']['limit']);
    }

    public function test_search_pipeline_filters_by_author(): void
    {
        // Arrange
        $author1 = $this->createAuthor(['name' => 'Author 1']);
        $author2 = $this->createAuthor(['name' => 'Author 2']);

        $page1 = $this->createPage(['status' => 'draft', 'title' => 'Page 1']);
        $page2 = $this->createPage(['status' => 'draft', 'title' => 'Page 2']);

        $this->attachAuthorToPage($page1, $author1);
        $this->attachAuthorToPage($page2, $author2);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('author', $author1->id);
        $results = $this->repository->searchPipeline($criteria);

        // Assert
        $this->assertCount(1, $results['draft']['cards']);
        $draftPageIds = $results['draft']['cards']->pluck('id')->toArray();
        $this->assertContains($page1->id, $draftPageIds);
        $this->assertNotContains($page2->id, $draftPageIds);
    }

    public function test_search_pipeline_filters_by_search_query(): void
    {
        // Arrange
        $this->createPage(['status' => 'draft', 'title' => 'Angular Tutorial']);
        $this->createPage(['status' => 'draft', 'title' => 'React Guide']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setSearchQuery('Angular');
        $results = $this->repository->searchPipeline($criteria);

        // Assert
        $this->assertCount(1, $results['draft']['cards']);
        $this->assertStringContainsString('Angular', $results['draft']['cards']->first()['title']);
    }

    public function test_search_pipeline_filters_by_page_type(): void
    {
        // Arrange
        $this->createPage(['status' => 'draft', 'page_type' => 'blog', 'title' => 'Blog Post']);
        $this->createPage(['status' => 'draft', 'page_type' => 'article', 'title' => 'Article']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('page_type', 'blog');
        $results = $this->repository->searchPipeline($criteria);

        // Assert
        $this->assertCount(1, $results['draft']['cards']);
        $this->assertEquals('blog', $results['draft']['cards']->first()['page_type']);
    }

    public function test_search_pipeline_filters_by_site(): void
    {
        // Arrange
        $site2 = $this->createSite();

        $page1 = $this->createPage(['status' => 'draft', 'site_id' => $this->siteId]);
        $page2 = $this->createPage(['status' => 'draft', 'site_id' => $site2->id]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('site_id', $this->siteId);
        $results = $this->repository->searchPipeline($criteria);

        // Assert
        $draftPageIds = $results['draft']['cards']->pluck('id')->toArray();
        $this->assertContains($page1->id, $draftPageIds);
        $this->assertNotContains($page2->id, $draftPageIds);
    }

    public function test_search_pipeline_loads_relationships(): void
    {
        // Arrange
        $author = $this->createAuthor(['name' => 'Test Author']);
        $tag = $this->createTag(['name' => 'Test Tag']);

        $page = $this->createPage(['status' => 'draft']);
        $this->attachAuthorToPage($page, $author);
        $this->attachTagToPage($page, $tag);
        $this->createPageMetadata($page->id);

        // Act
        $criteria = new SearchCriteria();
        $results = $this->repository->searchPipeline($criteria);

        // Assert
        $draftCard = $results['draft']['cards']->first();

        $this->assertNotEmpty($draftCard['pageAuthors']);
        $this->assertNotEmpty($draftCard['tags']);
        $this->assertNotEmpty($draftCard['metadata']);
    }

    public function test_update_page_status_changes_status(): void
    {
        // Arrange
        $page = $this->createPage(['status' => 'draft']);

        // Act
        $success = $this->repository->updatePageStatus($page->id, 'waiting_approval');

        // Assert
        $this->assertTrue($success);
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'status' => 'waiting_approval'
        ]);
    }

    public function test_update_page_status_returns_false_for_invalid_page(): void
    {
        // Act
        $success = $this->repository->updatePageStatus(99999, 'waiting_approval');

        // Assert
        $this->assertFalse($success);
    }

    public function test_update_page_status_sets_scheduled_at_when_moving_to_scheduled(): void
    {
        // Arrange
        $page = $this->createPage(['status' => 'draft', 'scheduled_at' => null]);

        // Act
        $this->repository->updatePageStatus($page->id, 'scheduled');

        // Assert
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'status' => 'scheduled'
        ]);

        $updatedPage = $this->repository->find($page->id);
        $this->assertNotNull($updatedPage->scheduled_at);
    }

    public function test_update_page_status_sets_published_at_when_moving_to_published(): void
    {
        // Arrange
        $page = $this->createPage(['status' => 'draft', 'published_at' => null]);

        // Act
        $this->repository->updatePageStatus($page->id, 'published');

        // Assert
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'status' => 'published'
        ]);

        $updatedPage = $this->repository->find($page->id);
        $this->assertNotNull($updatedPage->published_at);
    }

    public function test_update_page_status_does_not_overwrite_existing_scheduled_at(): void
    {
        // Arrange
        $scheduledAt = '2025-01-15 10:00:00';
        $page = $this->createPage([
            'status' => 'draft',
            'scheduled_at' => $scheduledAt
        ]);

        // Act
        $this->repository->updatePageStatus($page->id, 'scheduled');

        // Assert
        $updatedPage = $this->repository->find($page->id);
        $this->assertEquals($scheduledAt, $updatedPage->scheduled_at);
    }

    public function test_get_pipeline_metrics_returns_stage_counts(): void
    {
        // Arrange
        $this->createPages(3, ['status' => 'draft']);
        $this->createPages(2, ['status' => 'waiting_approval']);
        $this->createPages(1, ['status' => 'scheduled']);
        $this->createPages(5, ['status' => 'published']);

        // Act
        $metrics = $this->repository->getPipelineMetrics($this->siteId);

        // Assert
        $this->assertArrayHasKey('stage_counts', $metrics);
        $this->assertEquals(3, $metrics['stage_counts']['draft']);
        $this->assertEquals(2, $metrics['stage_counts']['waiting_approval']);
        $this->assertEquals(1, $metrics['stage_counts']['scheduled']);
        $this->assertEquals(5, $metrics['stage_counts']['published']);
    }

    public function test_get_pipeline_metrics_returns_throughput(): void
    {
        // Arrange
        $thirtyOneDaysAgo = date('Y-m-d H:i:s', strtotime('-31 days'));
        $twentyDaysAgo = date('Y-m-d H:i:s', strtotime('-20 days'));

        $this->createPage([
            'status' => 'published',
            'published_at' => $twentyDaysAgo
        ]);
        $this->createPage([
            'status' => 'published',
            'published_at' => $thirtyOneDaysAgo
        ]);

        // Act
        $metrics = $this->repository->getPipelineMetrics($this->siteId);

        // Assert
        $this->assertArrayHasKey('throughput', $metrics);
        $this->assertEquals(1, $metrics['throughput']);
    }

    public function test_get_pipeline_metrics_identifies_bottlenecks(): void
    {
        // Arrange - Create 8 draft pages (80% of 10 limit)
        $this->createPages(8, ['status' => 'draft']);

        // Act
        $metrics = $this->repository->getPipelineMetrics($this->siteId);

        // Assert
        $this->assertArrayHasKey('bottlenecks', $metrics);
        $this->assertContains('Draft', $metrics['bottlenecks']);
    }

    public function test_get_pipeline_metrics_identifies_multiple_bottlenecks(): void
    {
        // Arrange
        $this->createPages(8, ['status' => 'draft']); // 80% of 10
        $this->createPages(4, ['status' => 'waiting_approval']); // 80% of 5

        // Act
        $metrics = $this->repository->getPipelineMetrics($this->siteId);

        // Assert
        $this->assertCount(2, $metrics['bottlenecks']);
        $this->assertContains('Draft', $metrics['bottlenecks']);
        $this->assertContains('In Review', $metrics['bottlenecks']);
    }

    public function test_get_pipeline_metrics_filters_by_site(): void
    {
        // Arrange
        $site2 = $this->createSite();

        $this->createPages(3, ['status' => 'draft', 'site_id' => $this->siteId]);
        $this->createPages(5, ['status' => 'draft', 'site_id' => $site2->id]);

        // Act
        $metrics = $this->repository->getPipelineMetrics($this->siteId);

        // Assert
        $this->assertEquals(3, $metrics['stage_counts']['draft']);
    }

    public function test_get_pipeline_metrics_returns_avg_time_per_stage(): void
    {
        // Act
        $metrics = $this->repository->getPipelineMetrics($this->siteId);

        // Assert
        $this->assertArrayHasKey('avg_time_per_stage', $metrics);
        $this->assertArrayHasKey('draft', $metrics['avg_time_per_stage']);
        $this->assertArrayHasKey('waiting_approval', $metrics['avg_time_per_stage']);
        $this->assertArrayHasKey('scheduled', $metrics['avg_time_per_stage']);
        $this->assertArrayHasKey('published', $metrics['avg_time_per_stage']);
    }

    public function test_search_pipeline_returns_empty_stages_when_no_pages(): void
    {
        // Act
        $criteria = new SearchCriteria();
        $results = $this->repository->searchPipeline($criteria);

        // Assert
        $this->assertCount(0, $results['draft']['cards']);
        $this->assertCount(0, $results['waiting_approval']['cards']);
        $this->assertCount(0, $results['scheduled']['cards']);
        $this->assertCount(0, $results['published']['cards']);
    }

    public function test_search_pipeline_combines_multiple_filters(): void
    {
        // Arrange
        $author = $this->createAuthor(['name' => 'Test Author']);

        $matchingPage = $this->createPage([
            'status' => 'draft',
            'page_type' => 'blog',
            'title' => 'Angular Tutorial'
        ]);

        $nonMatchingPage1 = $this->createPage([
            'status' => 'draft',
            'page_type' => 'article',
            'title' => 'Angular Guide'
        ]);

        $nonMatchingPage2 = $this->createPage([
            'status' => 'draft',
            'page_type' => 'blog',
            'title' => 'React Tutorial'
        ]);

        $this->attachAuthorToPage($matchingPage, $author);
        $this->attachAuthorToPage($nonMatchingPage1, $author);

        // Act
        $criteria = new SearchCriteria();
        $criteria->addFilter('page_type', 'blog');
        $criteria->addFilter('author', $author->id);
        $criteria->setSearchQuery('Angular');

        $results = $this->repository->searchPipeline($criteria);

        // Assert
        $this->assertCount(1, $results['draft']['cards']);
        $draftPageIds = $results['draft']['cards']->pluck('id')->toArray();
        $this->assertContains($matchingPage->id, $draftPageIds);
    }
}