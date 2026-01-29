<?php

namespace App\Tests\Unit\Repositories\Newsletter;

use App\Models\Model;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Models\Page;
use App\Repositories\Newsletters\NewsletterSendPageViewRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterSendPageViewRepositoryTest extends FunctionalTestCase
{
    private NewsletterSendPageViewRepository $repository;
    private Model $send;
    private Model $page;

    public function testTrackPageView(): void
    {
        $this->repository->trackPageView(
            $this->send->id,
            $this->page->id,
            'test@example.com',
            '127.0.0.1',
            'Mozilla/5.0'
        );

        $views = $this->repository->getViewsForSend($this->send->id);

        $this->assertCount(1, $views);
        $this->assertEquals($this->page->id, $views[0]['page_id']);
        $this->assertEquals('test@example.com', $views[0]['email']);
    }

    public function testGetViewStatistics(): void
    {
        // Create multiple views
        $this->repository->trackPageView($this->send->id, $this->page->id, 'user1@example.com', '127.0.0.1', 'UA1');
        $this->repository->trackPageView($this->send->id, $this->page->id, 'user2@example.com', '127.0.0.2', 'UA2');
        $this->repository->trackPageView($this->send->id, $this->page->id, 'user1@example.com', '127.0.0.1', 'UA1'); // Duplicate user

        $stats = $this->repository->getViewStatistics($this->send->id);

        $this->assertEquals(3, $stats['total_clicks']);
        $this->assertEquals(2, $stats['unique_recipients']);
        $this->assertArrayHasKey('page_clicks', $stats);
        $this->assertEquals(3, $stats['page_clicks'][$this->page->id]);
    }

    public function testGetViewsForSendReturnsEmpty(): void
    {
        $views = $this->repository->getViewsForSend(99999);
        $this->assertEmpty($views);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new NewsletterSendPageViewRepository();

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $this->send = NewsletterSend::create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => now_datetime(),
            'recipient_count' => 100,
            'content_snapshot' => []
        ]);

        $this->page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'site_id' => $this->siteId,
            'status' => 'published',
            'published_at' => now_datetime()
        ]);
    }
}