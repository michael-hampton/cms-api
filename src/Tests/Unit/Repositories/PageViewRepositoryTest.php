<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Member;
use App\Models\Page;
use App\Models\PageView;
use App\Repositories\PageViewRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageViewRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private PageViewRepository $repository;
    private Page $page;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PageViewRepository();
        $this->page = $this->createPage();
        $this->member = $this->createMember();
    }

    public function testRecordView()
    {
        $view = $this->repository->recordView(
            $this->page->id,
            $this->member->id,
            $this->siteId,
            '127.0.0.1',
            'Mozilla/5.0',
            'https://example.com'
        );

        $this->assertInstanceOf(PageView::class, $view);
        $this->assertEquals($this->page->id, $view->page_id);
        $this->assertEquals($this->member->id, $view->member_id);
    }

    public function testGetPageViews()
    {
        $this->repository->recordView($this->page->id, $this->member->id, $this->siteId);
        $this->repository->recordView($this->page->id, null, $this->siteId, '192.168.1.1');

        $views = $this->repository->getPageViews($this->page->id);

        $this->assertCount(2, $views);
    }

    public function testGetPageViewsWithLimit()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->repository->recordView($this->page->id, $this->member->id, $this->siteId);
        }

        $views = $this->repository->getPageViews($this->page->id, 3);

        $this->assertCount(3, $views);
    }

    public function testGetMemberPageViews()
    {
        $page2 = $this->createPage(['slug' => 'test-page-2']);

        $this->repository->recordView($this->page->id, $this->member->id, $this->siteId);
        $this->repository->recordView($page2->id, $this->member->id, $this->siteId);

        $views = $this->repository->getMemberPageViews($this->member->id);

        $this->assertCount(2, $views);
    }

    public function testGetUniquePagesViewedByMember()
    {
        $page2 = $this->createPage(['slug' => 'test-page-2']);

        // View same page twice
        $this->repository->recordView($this->page->id, $this->member->id, $this->siteId);
        $this->repository->recordView($this->page->id, $this->member->id, $this->siteId);

        // View different page once
        $this->repository->recordView($page2->id, $this->member->id, $this->siteId);

        $count = $this->repository->getUniquePagesViewedByMember($this->member->id, $this->siteId);

        $this->assertEquals(2, $count);
    }

    public function testGetTotalViewsForPage()
    {
        $this->repository->recordView($this->page->id, $this->member->id, $this->siteId);
        $this->repository->recordView($this->page->id, $this->member->id, $this->siteId);
        $this->repository->recordView($this->page->id, null, $this->siteId);

        $count = $this->repository->getTotalViewsForPage($this->page->id);

        $this->assertEquals(3, $count);
    }

    public function testGetRecentlyViewedPages()
    {
        $page2 = $this->createPage(['slug' => 'test-page-2']);
        $page3 = $this->createPage(['slug' => 'test-page-3']);

        $this->repository->recordView($this->page->id, $this->member->id, $this->siteId);
        sleep(1);
        $this->repository->recordView($page2->id, $this->member->id, $this->siteId);
        sleep(1);
        $this->repository->recordView($page3->id, $this->member->id, $this->siteId);

        $recentPages = $this->repository->getRecentlyViewedPages($this->member->id, 2);

        $this->assertCount(2, $recentPages);
        // Most recent should be first
        $this->assertEquals($page3->id, $recentPages->first()->page_id);
    }
}