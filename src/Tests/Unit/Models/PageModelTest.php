<?php

namespace App\Tests\Unit\Models;

use App\Enums\PageStatus;
use App\Models\Author;
use App\Models\Block;
use App\Models\Category;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\PageProduct;
use App\Models\Site;
use App\Models\Tag;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testCreatePage()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'page_type' => 'standard',
        ]);

        $this->assertInstanceOf(Page::class, $page);
        $this->assertEquals('Test Page', $page->title);
        $this->assertEquals('test-page', $page->slug);
    }

    public function testPageHasTimestamps()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $this->assertNotNull($page->created_at);
        $this->assertNotNull($page->updated_at);
    }

    public function testPageBelongsToAuthor()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'author_id' => $author->id,
        ]);

        $pageAuthor = $page->author(true)->first();
        $this->assertInstanceOf(Author::class, $pageAuthor);
        $this->assertEquals('John Doe', $pageAuthor->name);
    }

    public function testPageHasManyBlocks()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        Block::create([
            'page_id' => $page->id,
            'type' => 'text',
            'content' => 'Block 1',
            'order' => 1,
            'data' => json_encode(['paragraphs' => ['Test paragraph']]),
        ]);

        Block::create([
            'page_id' => $page->id,
            'type' => 'text',
            'content' => 'Block 2',
            'order' => 2,
            'data' => json_encode(['paragraphs' => ['Test paragraph']]),
        ]);

        $blocks = $page->blocks(true)->get();

        $this->assertCount(2, $blocks);
        $this->assertEquals('text', $blocks->first()->type);
    }

    public function testPageBelongsToManyCategories()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);

        // Insert into pivot table
        $this->database->insert('page_categories', [
            'page_id' => $page->id,
            'category_id' => $category->id,
        ]);

        $categories = $page->categories(true)->get();
        $this->assertCount(1, $categories);
        $this->assertEquals('Test Category', $categories->first()->name);
    }

    public function testPageBelongsToManyTags()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $tag = Tag::create([
            'name' => 'Test Tag',
            'slug' => 'test-tag',
        ]);

        $this->database->insert('page_tags', [
            'page_id' => $page->id,
            'tag_id' => $tag->id,
        ]);

        $tags = $page->tags(true)->get();
        $this->assertCount(1, $tags);
        $this->assertEquals('Test Tag', $tags->first()->name);
    }

    public function testGetUrlAttribute()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $this->assertEquals('/test-page', $page->getUrlAttribute());
    }

    public function testIsPublished()
    {
        $page = Page::create([
            'title' => 'Published Page',
            'slug' => 'published-page',
            'status' => 'published',
        ]);

        $this->assertTrue($page->isPublished());
        $this->assertFalse($page->isDraft());
        $this->assertFalse($page->isArchived());
    }

    public function testIsDraft()
    {
        $page = Page::create([
            'title' => 'Draft Page',
            'slug' => 'draft-page',
            'status' => 'draft',
        ]);

        $this->assertTrue($page->isDraft());
        $this->assertFalse($page->isPublished());
        $this->assertFalse($page->isArchived());
    }

    public function testIsArchived()
    {
        $page = Page::create([
            'title' => 'Archived Page',
            'slug' => 'archived-page',
            'status' => 'archived',
        ]);

        $this->assertTrue($page->isArchived());
        $this->assertFalse($page->isPublished());
        $this->assertFalse($page->isDraft());
    }

    public function testScopePublished()
    {
        Page::create(['title' => 'Published', 'slug' => 'published', 'status' => 'published']);
        Page::create(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);

        $published = Page::published()->get();
        $this->assertCount(1, $published);
        $this->assertEquals('Published', $published->first()->title);
    }

    public function testScopeDraft()
    {
        Page::create(['title' => 'Published', 'slug' => 'published', 'status' => 'published']);
        Page::create(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);

        $drafts = Page::draft()->get();
        $this->assertCount(1, $drafts);
        $this->assertEquals('Draft', $drafts->first()->title);
    }

    public function testScopeBySlug()
    {
        Page::create(['title' => 'Page 1', 'slug' => 'page-1', 'status' => 'draft']);
        Page::create(['title' => 'Page 2', 'slug' => 'page-2', 'status' => 'draft']);

        $page = Page::bySlug('page-1')->first();
        $this->assertEquals('Page 1', $page->title);
    }

    public function testScopeByStatus()
    {
        Page::create(['title' => 'Published', 'slug' => 'published', 'status' => 'published']);
        Page::create(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);

        $published = Page::byStatus('published')->get();
        $this->assertCount(1, $published);
    }

    public function testUpdatePage()
    {
        $page = Page::create([
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'status' => 'draft',
        ]);

        $page->update([
            'title' => 'Updated Title',
        ]);

        $fresh = Page::find($page->id);

        $this->assertEquals('Updated Title', $fresh->title);
    }

    public function testDeletePage()
    {
        $page = Page::create([
            'title' => 'To Delete',
            'slug' => 'to-delete',
            'status' => 'draft',
        ]);

        $id = $page->id;
        $page->delete();

        $deleted = Page::find($id);
        $this->assertNull($deleted);
    }

    public function testSyncCustomFields()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $fieldDef = CustomFieldDefinition::create([
            'name' => 'Test Field',
            'key' => 'test_field',
            'type' => 'text',
        ]);

        $site = Site::create(['name' => 'Test Site', 'slug' => 'test-site']);;

        $page->syncCustomFields([
            $fieldDef->id => ['default_value' => 'Test Value']
        ], $site->id);

        $customFields = PageCustomField::where('page_id', $page->id)->get();
        $this->assertCount(1, $customFields);
        $this->assertEquals('Test Value', $customFields->first()->field_value);
    }

    public function testPublishedAtAttribute()
    {
        $date = '2024-01-15 10:30:00';
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => $date,
        ]);

        $this->assertEquals($date, $page->getPublishedAtAttribute());
    }

    public function testPageHasListingAttributes()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'listing_synopsis' => 'Test synopsis',
            'listing_title' => 'Listing Title',
            'listing_label' => 'Label',
            'listing_image_id' => 10,
            'listing_use_as_hero' => true,
            'hero_type' => 'image',
            'hero_image_id' => 7,
            'hero_video_url' => '',
            'crop_overrides' => json_encode(['homepage-card' => ['imageId' => 10]]),
            'resolved_images' => json_encode(['homepage-card' => ['image_id' => 10]])
        ]);

        $this->assertEquals('Test synopsis', $page->listing_synopsis);
        $this->assertEquals('Listing Title', $page->listing_title);
        $this->assertEquals('Label', $page->listing_label);
        $this->assertEquals(10, $page->listing_image_id);
        $this->assertTrue($page->listing_use_as_hero);
        $this->assertEquals('image', $page->hero_type);
        $this->assertEquals(7, $page->hero_image_id);
        $this->assertIsArray($page->crop_overrides);
        $this->assertIsArray($page->resolved_images);
    }

    public function testPageCastsJsonFields()
    {
        $cropOverrides = ['homepage-card' => ['imageId' => 10, 'ratio' => '1:1']];
        $resolvedImages = ['homepage-card' => ['image_id' => 10]];

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
            'crop_overrides' => $cropOverrides,
            'resolved_images' => $resolvedImages
        ]);

        $fresh = Page::find($page->id);

        $this->assertIsArray($fresh->crop_overrides);
        $this->assertIsArray($fresh->resolved_images);
        $this->assertEquals($cropOverrides, $fresh->crop_overrides);
        $this->assertEquals($resolvedImages, $fresh->resolved_images);
    }

    public function test_page_has_products_relationship(): void
    {
        $page = $this->createPage();
        $product1 = $this->createProduct(['name' => 'Product 1']);
        $product2 = $this->createProduct(['name' => 'Product 2']);

        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product1->id,
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);
        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product2->id,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $products = $page->products();
        $products = $products->toArray();

        $this->assertCount(2, $products);
        $this->assertEquals('Product 1', $products[0]['name']);
        $this->assertEquals('Product 2', $products[1]['name']);
    }

    public function test_page_products_ordered_by_sort_order(): void
    {
        $page = $this->createPage();
        $product1 = $this->createProduct(['name' => 'Product 1']);
        $product2 = $this->createProduct(['name' => 'Product 2']);
        $product3 = $this->createProduct(['name' => 'Product 3']);

        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product2->id,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);
        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product3->id,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);
        PageProduct::create([
            'page_id' => $page->id,
            'product_id' => $product1->id,
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);

        $products = $page->products();

        $this->assertEquals('Product 1', $products->first()->name);
        $this->assertEquals('Product 3', $products->last()->name);
    }

    public function testPageStatusIsEnum()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $this->assertEquals(PageStatus::DRAFT->value, $page->status);
    }

    public function testRequiresApproval()
    {
        $page = $this->createPage(['status' => 'draft', 'requires_approval' => true]);

        $this->assertTrue($page->requiresApproval());
    }

    public function testDoesNotRequireApproval()
    {
        $page = $this->createPage(['status' => 'draft', 'requires_approval' => false]);

        $this->assertFalse($page->requiresApproval());
    }

    public function testIsApproved()
    {
        $page = $this->createPage(['status' => 'waiting_approval', 'approved_by' => 1, 'approved_at' => date('Y-m-d H:i:s')]);;

        $this->assertTrue($page->isApproved());
    }

    public function testIsNotApproved()
    {
        $page = $this->createPage(['status' => 'waiting_approval']);

        $this->assertFalse($page->isApproved());
    }

    public function testIsWaitingApproval()
    {
        $page = $this->createPage(['status' => 'waiting_approval']);

        $this->assertTrue($page->isWaitingApproval());
    }

    public function testIsPrivate()
    {
        $page = $this->createPage(['status' => 'private']);

        $this->assertTrue($page->isPrivate());
    }

    public function testIsOnHold()
    {
        $page = $this->createPage(['status' => 'on_hold']);

        $this->assertTrue($page->isOnHold());
    }

    public function testCanTransitionFromDraftToPublished()
    {
        $page = $this->createPage(['status' => 'draft']);

        $this->assertTrue($page->canTransitionTo(PageStatus::PUBLISHED));
        $this->assertTrue($page->canTransitionTo('published'));
    }

    public function testCanTransitionFromDraftToWaitingApproval()
    {
        $page = $this->createPage(['status' => 'draft']);

        $this->assertTrue($page->canTransitionTo(PageStatus::WAITING_APPROVAL));
    }

    public function testCannotTransitionFromArchivedToPublished()
    {
        $page = $this->createPage(['status' => 'archived']);

        $this->assertFalse($page->canTransitionTo(PageStatus::PUBLISHED));
    }

    public function testCanTransitionFromArchivedToDraft()
    {
        $page = $this->createPage(['status' => 'archived']);

        $this->assertTrue($page->canTransitionTo(PageStatus::DRAFT));
    }

    public function testCanTransitionFromWaitingApprovalToPublished()
    {
        $page = $this->createPage(['status' => 'waiting_approval']);

        $this->assertTrue($page->canTransitionTo(PageStatus::PUBLISHED));
    }

    public function testCanTransitionFromPublishedToPrivate()
    {
        $page = $this->createPage(['status' => 'published']);

        $this->assertTrue($page->canTransitionTo(PageStatus::PRIVATE));
    }

    public function testApprovePage()
    {
        $page = $this->createPage(['status' => 'waiting_approval']);

        $page->approve(1);

        $this->assertEquals(1, $page->approved_by);
        $this->assertNotNull($page->approved_at);
    }

    public function testRemoveApproval()
    {
        $page = $this->createPage(['status' => 'waiting_approval', 'approved_by' => 1, 'approved_at' => date('Y-m-d H:i:s')]);

        $page->removeApproval();

        $this->assertNull($page->approved_by);
        $this->assertNull($page->approved_at);
    }

    public function testGetValidStatuses()
    {
        $statuses = Page::getValidStatuses();

        $this->assertIsArray($statuses);
        $this->assertContains('draft', $statuses);
        $this->assertContains('published', $statuses);
        $this->assertContains('waiting_approval', $statuses);
        $this->assertContains('private', $statuses);
        $this->assertContains('on_hold', $statuses);
        $this->assertCount(8, $statuses);
    }

    public function testScopeWaitingApproval()
    {
        $this->createPage(['status' => 'draft', 'title' => 'Draft']);
        $this->createPage(['status' => 'waiting_approval', 'title' => 'Waiting']);

        $waiting = Page::byStatus(PageStatus::WAITING_APPROVAL->value)->get();

        $this->assertCount(1, $waiting);
        $this->assertEquals('Waiting', $waiting->first()->title);
    }

    public function testScopePrivate()
    {
        $this->createPage(['status' => 'draft', 'title' => 'Draft']);
        $this->createPage(['status' => 'private', 'title' => 'Private']);

        $private = Page::byStatus('private')->get();

        $this->assertCount(1, $private);
        $this->assertEquals('Private', $private->first()->title);
    }

    public function testScopeOnHold()
    {
        $this->createPage(['status' => 'draft', 'title' => 'Draft']);
        $this->createPage(['status' => 'on_hold', 'title' => 'On Hold']);

        $onHold = Page::byStatus(PageStatus::ON_HOLD->value)->get();

        $this->assertCount(1, $onHold);
        $this->assertEquals('On Hold', $onHold->first()->title);
    }

    public function testIsInternal()
    {
        $page = $this->createPage(['status' => 'internal']);

        $this->assertTrue($page->isInternal());
    }

    public function testCanTransitionFromDraftToInternal()
    {
        $page = $this->createPage(['status' => 'draft']);

        $this->assertTrue($page->canTransitionTo(PageStatus::INTERNAL));
    }

    public function testCanTransitionFromInternalToPublished()
    {
        $page = $this->createPage(['status' => 'internal']);

        $this->assertTrue($page->canTransitionTo(PageStatus::PUBLISHED));
    }

    public function testGetValidStatusesIncludesInternal()
    {
        $statuses = Page::getValidStatuses();

        $this->assertContains('internal', $statuses);
        $this->assertCount(8, $statuses); // Updated count
    }

    public function testScopeInternal()
    {
        $this->createPage(['status' => 'draft', 'title' => 'Draft']);
        $this->createPage(['status' => 'internal', 'title' => 'Internal']);

        $internal = Page::byStatus('internal')->get();

        $this->assertCount(1, $internal);
        $this->assertEquals('Internal', $internal->first()->title);
    }
}