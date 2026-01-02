<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Page;
use App\Models\PageLike;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageLikeModelTest extends FunctionalTestCase
{
    use CreatesTestData;
    private Page $page;
    private Member $member;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create(['name' => 'Test Site', 'domain' => 'test.com']);

        $this->page = $this->createPage();

        $this->member = $this->createMember();
        $this->actingAsMember($this->member);
    }

    public function testToggleLike()
    {
        // First toggle - should create like
        $result = PageLike::toggle($this->page->id, $this->member->id, $this->site->id);

        $this->assertTrue($result['liked']);
        $this->assertEquals(1, $result['like_count']);

        // Second toggle - should remove like
        $result = PageLike::toggle($this->page->id, $this->member->id, $this->site->id);

        $this->assertFalse($result['liked']);
        $this->assertEquals(0, $result['like_count']);
    }

    public function testIsLikedBy()
    {
        $this->assertFalse(PageLike::isLikedBy($this->page->id, $this->member->id, $this->site->id));

        PageLike::toggle($this->page->id, $this->member->id, $this->site->id);

        $this->assertTrue(PageLike::isLikedBy($this->page->id, $this->member->id, $this->site->id));
    }

    public function testGetLikeCount()
    {
        $member2 = Member::create([
            'email' => 'test2@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User 2',
            'site_id' => $this->site->id,
            'is_active' => true
        ]);

        PageLike::toggle($this->page->id, $this->member->id, $this->site->id);
        PageLike::toggle($this->page->id, $member2->id, $this->site->id);

        $count = PageLike::getLikeCount($this->page->id);
        $this->assertEquals(2, $count);
    }

    public function testGetMemberLikeCount()
    {
        $page2 = Page::create([
            'title' => 'Test Page 2',
            'slug' => 'test-page-2',
            'site_id' => $this->site->id,
            'status' => 'published'
        ]);

        PageLike::toggle($this->page->id, $this->member->id, $this->site->id);
        PageLike::toggle($page2->id, $this->member->id, $this->site->id);

        $count = PageLike::getMemberLikeCount($this->member->id, $this->site->id);
        $this->assertEquals(2, $count);
    }

    public function testGetMemberLikedPages()
    {
        $page2 = Page::create([
            'title' => 'Test Page 2',
            'slug' => 'test-page-2',
            'site_id' => $this->site->id,
            'status' => 'published'
        ]);

        PageLike::toggle($this->page->id, $this->member->id, $this->site->id);
        PageLike::toggle($page2->id, $this->member->id, $this->site->id);

        $likedPages = PageLike::getMemberLikedPages($this->member->id, $this->site->id);

        $this->assertCount(2, $likedPages);
    }

    public function testUniqueConstraint()
    {
        PageLike::create([
            'page_id' => $this->page->id,
            'member_id' => $this->member->id,
            'site_id' => $this->site->id,
            'liked_at' => date('Y-m-d H:i:s')
        ]);

        $this->expectException(\Exception::class);

        PageLike::create([
            'page_id' => $this->page->id,
            'member_id' => $this->member->id,
            'site_id' => $this->site->id,
            'liked_at' => date('Y-m-d H:i:s')
        ]);
    }
}