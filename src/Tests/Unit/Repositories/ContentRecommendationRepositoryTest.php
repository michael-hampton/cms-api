<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Comment;
use App\Models\MemberReadingPreference;
use App\Models\PageLike;
use App\Models\PageView;
use App\Repositories\Recommendations\ContentRecommendationRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ContentRecommendationRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ContentRecommendationRepository $repository;

    public function testGetOrCreatePreferencesCreatesNewRecord(): void
    {
        $member = $this->createMember();

        $preferences = $this->repository->getOrCreatePreferences($member->id, $this->siteId);

        $this->assertInstanceOf(MemberReadingPreference::class, $preferences);
        $this->assertEquals($member->id, $preferences->member_id);
        $this->assertEquals($this->siteId, $preferences->site_id);
        $this->assertEquals([], $preferences->preferred_categories);
        $this->assertEquals(0, $preferences->engagement_score);
    }

    public function testGetOrCreatePreferencesReturnsExisting(): void
    {
        $member = $this->createMember();

        $existing = MemberReadingPreference::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'preferred_categories' => [1, 2],
            'engagement_score' => 50
        ]);

        $preferences = $this->repository->getOrCreatePreferences($member->id, $this->siteId);

        $this->assertEquals($existing->id, $preferences->id);
        $this->assertEquals([1, 2], $preferences->preferred_categories);
        $this->assertEquals(50, $preferences->engagement_score);
    }

    public function testUpdatePreferencesFromActivityTracksCategories(): void
    {
        $member = $this->createMember();
        $category1 = $this->createCategory(['name' => 'Tech']);
        $category2 = $this->createCategory(['name' => 'Sports']);

        // Create pages with categories
        $page1 = $this->createPage();
        $page1->categories(true)->attach($category1->id);

        $page2 = $this->createPage();
        $page2->categories(true)->attach($category1->id);

        $page3 = $this->createPage();
        $page3->categories(true)->attach($category2->id);

        // Create page views
        PageView::create(['member_id' => $member->id, 'page_id' => $page1->id, 'site_id' => $this->siteId, 'viewed_at' => now_datetime()->format('Y-m-d H:i:s')]);
        PageView::create(['member_id' => $member->id, 'page_id' => $page2->id, 'site_id' => $this->siteId, 'viewed_at' => now_datetime()->format('Y-m-d H:i:s')]);
        PageView::create(['member_id' => $member->id, 'page_id' => $page3->id, 'site_id' => $this->siteId, 'viewed_at' => now_datetime()->format('Y-m-d H:i:s')]);

        $this->repository->updatePreferencesFromActivity($member->id, $this->siteId);

        $preferences = MemberReadingPreference::where('member_id', $member->id)->first();

        $this->assertContains($category1->id, $preferences->preferred_categories); //todo: fix this
        //$this->assertContains($category2->id, $preferences->preferred_categories);
    }

    public function testUpdatePreferencesCalculatesEngagementScore(): void
    {
        $member = $this->createMember();
        $page = $this->createPage();

        // Create activity
        PageView::create(['member_id' => $member->id, 'page_id' => $page->id, 'site_id' => $this->siteId, 'viewed_at' => now_datetime()->format('Y-m-d H:i:s')]);
        PageView::create(['member_id' => $member->id, 'page_id' => $page->id, 'site_id' => $this->siteId, 'viewed_at' => now_datetime()->subDays(1)->format('Y-m-d H:i:s')]);;

        PageLike::create(['member_id' => $member->id, 'page_id' => $page->id, 'site_id' => $this->siteId, 'liked_at' => now_datetime()->format('Y-m-d H:i:s')]);;

        Comment::create([
            'member_id' => $member->id,
            'page_id' => $page->id,
            'site_id' => $this->siteId,
            'email' => $member->email,
            'content' => 'Great article!',
            'status' => 'approved'
        ]);

        $this->repository->updatePreferencesFromActivity($member->id, $this->siteId);

        $preferences = MemberReadingPreference::where('member_id', $member->id)->first();

        // Score should be: (2 views * 1) + (1 like * 3) + (1 comment * 5) = 10
        $this->assertEquals(10, $preferences->engagement_score);
    }

    public function testGetRecommendedPagesExcludesViewedPages(): void
    {
        $member = $this->createMember();

        $viewedPage = $this->createPage(['status' => 'published']);
        $unviewedPage = $this->createPage(['status' => 'published']);

        PageView::create(['member_id' => $member->id, 'page_id' => $viewedPage->id, 'site_id' => $this->siteId, 'viewed_at' => now_datetime()->format('Y-m-d H:i:s')]);

        $recommendations = $this->repository->getRecommendedPages($member->id, $this->siteId, 10);

        $pageIds = $recommendations->pluck('id')->toArray();
        $this->assertNotContains($viewedPage->id, $pageIds);
        $this->assertContains($unviewedPage->id, $pageIds);
    }

    public function testGetRecommendedPagesUsesPreferences(): void
    {
        $member = $this->createMember();
        $category = $this->createCategory();

        MemberReadingPreference::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'preferred_categories' => [$category->id]
        ]);

        $matchingPage = $this->createPage(['status' => 'published']);
        $matchingPage->categories(true)->attach($category->id);

        $nonMatchingPage = $this->createPage(['status' => 'published']);

        $recommendations = $this->repository->getRecommendedPages($member->id, $this->siteId, 10);

        $this->assertTrue($recommendations->contains('id', $matchingPage->id));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ContentRecommendationRepository();
    }
}