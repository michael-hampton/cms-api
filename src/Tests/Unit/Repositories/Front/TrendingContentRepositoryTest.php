<?php

namespace App\Tests\Unit\Repositories\Front;

use App\Models\Comment;
use App\Models\PageLike;
use App\Models\PageView;
use App\Models\TrendingContent;
use App\Repositories\Recommendations\TrendingContentRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class TrendingContentRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private TrendingContentRepository $repository;

    public function testCalculateTrendingScoresCreatesRecords(): void
    {
        $page = $this->createPage(['status' => 'published']);
        $member = $this->createMember();

        // Create activity
        PageView::create(['page_id' => $page->id, 'member_id' => $member->id, 'site_id' => $this->siteId, 'viewed_at' => now_datetime()->format('Y-m-d H:i:s')]);;
        PageLike::create(['page_id' => $page->id, 'member_id' => $member->id, 'site_id' => $this->siteId, 'liked_at' => now_datetime()->format('Y-m-d H:i:s')]);;;
        Comment::create([
            'page_id' => $page->id,
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'email' => $member->email,
            'content' => 'Comment',
            'status' => 'approved'
        ]);

        $this->repository->calculateTrendingScores($this->siteId);

        $trending = TrendingContent::where('page_id', $page->id)->first();

        $this->assertNotNull($trending);
        $this->assertEquals(1, $trending->view_count_24h);
        $this->assertEquals(1, $trending->like_count_24h);
        $this->assertEquals(1, $trending->comment_count_24h);
// Score: (11) + (15) + (1*10) = 16
        $this->assertEquals(16, $trending->trending_score);
    }

    public function testCalculateTrendingScoresOnlyLast24Hours(): void
    {
        $page = $this->createPage(['status' => 'published']);
        $member = $this->createMember();

        // Create old activity (more than 24 hours ago)
        $oldView = PageView::create([
            'page_id' => $page->id,
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'created_at' => now_datetime()->subHours(25),
            'viewed_at' => now_datetime()->subHours(25)->format('Y-m-d H:i:s')
        ]);

        // Create recent activity
        PageView::create(['page_id' => $page->id, 'member_id' => $member->id, 'site_id' => $this->siteId, 'viewed_at' => now_datetime()->format('Y-m-d H:i:s')]);;;

        $this->repository->calculateTrendingScores($this->siteId);

        $trending = TrendingContent::where('page_id', $page->id)->first();

        $this->assertEquals(1, $trending->view_count_24h);
    }

    public function testGetTrendingPagesReturnsOrderedByScore(): void
    {
        $page1 = $this->createPage(['status' => 'published', 'title' => 'Page 1']);
        $page2 = $this->createPage(['status' => 'published', 'title' => 'Page 2']);

        TrendingContent::create([
            'page_id' => $page1->id,
            'site_id' => $this->siteId,
            'trending_score' => 10
        ]);

        TrendingContent::create([
            'page_id' => $page2->id,
            'site_id' => $this->siteId,
            'trending_score' => 20
        ]);

        $trending = $this->repository->getTrendingPages($this->siteId, 10);

        $this->assertEquals($page2->id, $trending->first()->id);
        $this->assertEquals($page1->id, $trending->last()->id);
    }

    public function testGetTrendingConversationsOrdersByComments(): void
    {
        $page1 = $this->createPage(['status' => 'published']);
        $page2 = $this->createPage(['status' => 'published']);

        TrendingContent::create([
            'page_id' => $page1->id,
            'site_id' => $this->siteId,
            'comment_count_24h' => 5,
            'trending_score' => 50
        ]);

        TrendingContent::create([
            'page_id' => $page2->id,
            'site_id' => $this->siteId,
            'comment_count_24h' => 10,
            'trending_score' => 100
        ]);

        $trending = $this->repository->getTrendingConversations($this->siteId, 10);

        $this->assertEquals($page2->id, $trending->first()->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TrendingContentRepository();
    }
}