<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Page;
use App\Models\PageView;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageViewModelTest extends FunctionalTestCase
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
    }

    public function testRecordView()
    {
        $view = PageView::recordView(
            $this->page->id,
            $this->member->id,
            $this->site->id,
            '127.0.0.1',
            'Mozilla/5.0',
            'https://example.com'
        );

        $this->assertInstanceOf(PageView::class, $view);
        $this->assertEquals($this->page->id, $view->page_id);
        $this->assertEquals($this->member->id, $view->member_id);
        $this->assertEquals('127.0.0.1', $view->ip_address);
    }

    public function testRecordAnonymousView()
    {
        $view = PageView::recordView(
            $this->page->id,
            null,
            $this->site->id,
            '127.0.0.1'
        );

        $this->assertNull($view->member_id);
        $this->assertEquals($this->page->id, $view->page_id);
    }

    public function testGetTotalViewCount()
    {
        PageView::recordView($this->page->id, $this->member->id, $this->site->id);
        PageView::recordView($this->page->id, $this->member->id, $this->site->id);
        PageView::recordView($this->page->id, null, $this->site->id, '192.168.1.1');

        $count = PageView::getTotalViewCount($this->page->id);
        $this->assertEquals(3, $count);
    }

    public function testGetMemberViewCount()
    {
        $page2 = Page::create([
            'title' => 'Test Page 2',
            'slug' => 'test-page-2',
            'site_id' => $this->site->id,
            'status' => 'published'
        ]);

        PageView::recordView($this->page->id, $this->member->id, $this->site->id);
        PageView::recordView($page2->id, $this->member->id, $this->site->id);
        PageView::recordView($this->page->id, $this->member->id, $this->site->id); // duplicate

        $count = PageView::getMemberViewCount($this->member->id, $this->site->id);
        $this->assertEquals(2, $count); // Only unique pages
    }

    public function testPageRelationship()
    {
        $view = PageView::recordView($this->page->id, $this->member->id, $this->site->id);

        $relatedPage = $view->page();
        $this->assertInstanceOf(Page::class, $relatedPage);
        $this->assertEquals($this->page->id, $relatedPage->id);
    }

    public function testMemberRelationship()
    {
        $view = PageView::recordView($this->page->id, $this->member->id, $this->site->id);

        $relatedMember = $view->member();
        $this->assertInstanceOf(Member::class, $relatedMember);
        $this->assertEquals($this->member->id, $relatedMember->id);
    }
}