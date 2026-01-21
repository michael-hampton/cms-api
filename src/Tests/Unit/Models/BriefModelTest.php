<?php

namespace App\Tests\Unit\Models;

use App\Models\Brief;
use App\Models\BriefAttachment;
use App\Models\BriefComment;
use App\Models\Category;
use App\Models\Page;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BriefModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateBrief()
    {
        $user = $this->createUser();

        $brief = Brief::create([
            'title' => 'Test Brief',
            'description' => 'Test description',
            'owner_id' => $user->id,
            'site_id' => $this->siteId,
            'status' => 'draft'
        ]);

        $this->assertInstanceOf(Brief::class, $brief);
        $this->assertEquals('Test Brief', $brief->title);
        $this->assertEquals('draft', $brief->status);
    }

    public function testBriefHasTimestamps()
    {
        $user = $this->createUser();

        $brief = Brief::create([
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        $this->assertNotNull($brief->created_at);
        $this->assertNotNull($brief->updated_at);
    }

    public function testBriefBelongsToOwner()
    {
        $user = $this->createUser(['name' => 'John Doe']);

        $brief = Brief::create([
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        $owner = $brief->owner;
        $this->assertInstanceOf(User::class, $owner);
        $this->assertEquals('John Doe', $owner->name);
    }

    public function testBriefBelongsToCategory()
    {
        $user = $this->createUser();
        $category = $this->createCategory(['name' => 'Tech']);

        $brief = Brief::create([
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'site_id' => $this->siteId
        ]);

        $briefCategory = $brief->category(true)->first();
        $this->assertInstanceOf(Category::class, $briefCategory);
        $this->assertEquals('Tech', $briefCategory->name);
    }

    public function testBriefHasManyAttachments()
    {
        $user = $this->createUser();
        $brief = Brief::create([
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        BriefAttachment::create([
            'brief_id' => $brief->id,
            'type' => 'image',
            'file_url' => 'http://example.com/image.jpg',
            'file_name' => 'image.jpg',
            'sort_order' => 0
        ]);

        BriefAttachment::create([
            'brief_id' => $brief->id,
            'type' => 'product',
            'url' => 'http://example.com/product',
            'sort_order' => 1
        ]);

        $attachments = $brief->attachments(true)->get();
        $this->assertCount(2, $attachments);
        $this->assertEquals('image', $attachments->first()->type);
    }

    public function testBriefHasManyComments()
    {
        $user = $this->createUser();
        $brief = Brief::create([
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        BriefComment::create([
            'brief_id' => $brief->id,
            'user_id' => $user->id,
            'content' => 'First comment'
        ]);

        BriefComment::create([
            'brief_id' => $brief->id,
            'user_id' => $user->id,
            'content' => 'Second comment'
        ]);

        $comments = $brief->comments(true)->get();
        $this->assertCount(2, $comments);
        $this->assertEquals('First comment', $comments->first()->content);
    }

    public function testBriefBelongsToConvertedPage()
    {
        $user = $this->createUser();
        $page = $this->createPage(['title' => 'Converted Page']);

        $brief = Brief::create([
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId,
            'status' => 'converted',
            'converted_page_id' => $page->id,
            'converted_at' => date('Y-m-d H:i:s')
        ]);

        $convertedPage = $brief->convertedPage(true)->first();
        $this->assertInstanceOf(Page::class, $convertedPage);
        $this->assertEquals('Converted Page', $convertedPage->title);
    }

    public function testIsActive()
    {
        $user = $this->createUser();

        $brief = Brief::create([
            'title' => 'Active Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId,
            'status' => 'draft',
            'is_active' => true
        ]);

        $this->assertTrue($brief->isActive());
        $this->assertFalse($brief->isConverted());
        $this->assertFalse($brief->isArchived());
    }

    public function testIsConverted()
    {
        $user = $this->createUser();

        $brief = Brief::create([
            'title' => 'Converted Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId,
            'status' => 'converted'
        ]);

        $this->assertTrue($brief->isConverted());
        $this->assertFalse($brief->isArchived());
    }

    public function testIsArchived()
    {
        $user = $this->createUser();

        $brief = Brief::create([
            'title' => 'Archived Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId,
            'status' => 'archived'
        ]);

        $this->assertTrue($brief->isArchived());
        $this->assertFalse($brief->isConverted());
    }

    public function testUpdateBrief()
    {
        $user = $this->createUser();

        $brief = Brief::create([
            'title' => 'Original Title',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        $brief->update(['title' => 'Updated Title']);

        $fresh = Brief::find($brief->id);
        $this->assertEquals('Updated Title', $fresh->title);
    }

    public function testDeleteBrief()
    {
        $user = $this->createUser();

        $brief = Brief::create([
            'title' => 'To Delete',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        $id = $brief->id;
        $brief->delete();

        $deleted = Brief::find($id);
        $this->assertNull($deleted);
    }

    public function testConvertedAtCasting()
    {
        $user = $this->createUser();
        $date = '2025-01-15 10:30:00';

        $brief = Brief::create([
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId,
            'status' => 'converted',
            'converted_at' => $date
        ]);

        $this->assertInstanceOf(\DateTime::class, $brief->converted_at);
        $this->assertEquals($date, $brief->converted_at->format('Y-m-d H:i:s'));
    }

    public function testAttachmentsOrderedBySortOrder()
    {
        $user = $this->createUser();
        $brief = Brief::create([
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        BriefAttachment::create([
            'brief_id' => $brief->id,
            'type' => 'image',
            'file_url' => 'second.jpg',
            'sort_order' => 1
        ]);

        BriefAttachment::create([
            'brief_id' => $brief->id,
            'type' => 'image',
            'file_url' => 'first.jpg',
            'sort_order' => 0
        ]);

        $attachments = $brief->attachments(true)->get();
        $this->assertEquals('first.jpg', $attachments->first()->file_url);
        $this->assertEquals('second.jpg', $attachments->last()->file_url);
    }

    public function testCommentHasReplies()
    {
        $user = $this->createUser();
        $brief = Brief::create([
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        $parentComment = BriefComment::create([
            'brief_id' => $brief->id,
            'user_id' => $user->id,
            'content' => 'Parent comment'
        ]);

        BriefComment::create([
            'brief_id' => $brief->id,
            'user_id' => $user->id,
            'parent_comment_id' => $parentComment->id,
            'content' => 'Reply 1'
        ]);

        BriefComment::create([
            'brief_id' => $brief->id,
            'user_id' => $user->id,
            'parent_comment_id' => $parentComment->id,
            'content' => 'Reply 2'
        ]);

        $replies = $parentComment->replies(true)->get();
        $this->assertCount(2, $replies);
        $this->assertEquals('Reply 1', $replies->first()->content);
    }

    public function testBriefCommentsExcludeReplies()
    {
        $user = $this->createUser();
        $brief = Brief::create([
            'title' => 'Test Brief',
            'owner_id' => $user->id,
            'site_id' => $this->siteId
        ]);

        $parentComment = BriefComment::create([
            'brief_id' => $brief->id,
            'user_id' => $user->id,
            'content' => 'Parent comment'
        ]);

        BriefComment::create([
            'brief_id' => $brief->id,
            'user_id' => $user->id,
            'parent_comment_id' => $parentComment->id,
            'content' => 'Reply'
        ]);

        // Brief comments should only return top-level comments
        $comments = $brief->comments(true)->get();
        $this->assertCount(1, $comments);
        $this->assertEquals('Parent comment', $comments->first()->content);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}