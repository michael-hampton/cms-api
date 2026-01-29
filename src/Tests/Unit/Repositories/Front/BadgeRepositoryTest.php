<?php

namespace App\Tests\Unit\Repositories\Front;

use App\Models\Badge;
use App\Models\MemberActivity;
use App\Models\MemberBadge;
use App\Models\MemberPoint;
use App\Repositories\Members\BadgeRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class BadgeRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private BadgeRepository $repository;

    public function test_get_active_badges_for_site_returns_only_active_badges(): void
    {
        $activeBadge1 = $this->createBadge([
            'name' => 'Active Badge 1',
            'site_id' => $this->siteId,
            'is_active' => true
        ]);
        $activeBadge2 = $this->createBadge([
            'name' => 'Active Badge 2',
            'site_id' => $this->siteId,
            'is_active' => true
        ]);
        $inactiveBadge = $this->createBadge([
            'name' => 'Inactive Badge',
            'site_id' => $this->siteId,
            'is_active' => false
        ]);

        $badges = $this->repository->getActiveBadgesForSite($this->siteId);

        $this->assertCount(2, $badges);
        $this->assertCollectionContains($badges, ['id' => $activeBadge1->id]);
        $this->assertCollectionContains($badges, ['id' => $activeBadge2->id]);
        $this->assertCollectionDoesNotContain($badges, ['id' => $inactiveBadge->id]);
    }

    public function test_get_active_badges_for_site_filters_by_site(): void
    {
        $badge1 = $this->createBadge([
            'name' => 'Site 1 Badge',
            'site_id' => $this->siteId,
            'is_active' => true
        ]);

        $otherSite = $this->createSite();
        $badge2 = $this->createBadge([
            'name' => 'Site 2 Badge',
            'site_id' => $otherSite->id,
            'is_active' => true
        ]);

        $badges = $this->repository->getActiveBadgesForSite($this->siteId);

        $this->assertCollectionContains($badges, ['id' => $badge1->id]);
        $this->assertCollectionDoesNotContain($badges, ['id' => $badge2->id]);
    }

    public function test_find_member_badge_returns_correct_badge(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);
        $badge = $this->createBadge(['site_id' => $this->siteId]);

        $memberBadge = $this->createMemberBadge([
            'member_id' => $member->id,
            'badge_id' => $badge->id
        ]);

        $found = $this->repository->findMemberBadge($member->id, $badge->id);

        $this->assertNotNull($found);
        $this->assertEquals($memberBadge->id, $found->id);
        $this->assertEquals($member->id, $found->member_id);
        $this->assertEquals($badge->id, $found->badge_id);
    }

    public function test_find_member_badge_returns_null_when_not_found(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);
        $badge = $this->createBadge(['site_id' => $this->siteId]);

        $found = $this->repository->findMemberBadge($member->id, $badge->id);

        $this->assertNull($found);
    }

    public function test_create_member_activity_stores_activity(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);

        $data = [
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'comment_created',
            'entity_type' => 'comment',
            'entity_id' => 123,
            'metadata' => ['text' => 'Great post!'],
            'points' => 10,
            'activity_date' => now()
        ];

        $activity = $this->repository->createMemberActivity($data);

        $this->assertInstanceOf(MemberActivity::class, $activity);
        $this->assertEquals($member->id, $activity->member_id);
        $this->assertEquals('comment_created', $activity->activity_type);
        $this->assertEquals('comment', $activity->entity_type);
        $this->assertEquals(123, $activity->entity_id);
        $this->assertEquals(10, $activity->points);

        $this->assertDatabaseHas('member_activities', [
            'member_id' => $member->id,
            'activity_type' => 'comment_created',
            'points' => 10
        ]);
    }

    public function test_create_member_point_stores_points(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);

        $data = [
            'member_id' => $member->id,
            'points' => 50,
            'reason' => 'First comment',
            'reference_type' => 'activity',
            'reference_id' => 1,
            'awarded_at' => now()
        ];

        $memberPoint = $this->repository->createMemberPoint($data);

        $this->assertInstanceOf(MemberPoint::class, $memberPoint);
        $this->assertEquals($member->id, $memberPoint->member_id);
        $this->assertEquals(50, $memberPoint->points);
        $this->assertEquals('First comment', $memberPoint->reason);
        $this->assertEquals('activity', $memberPoint->reference_type);

        $this->assertDatabaseHas('member_points', [
            'member_id' => $member->id,
            'points' => 50,
            'reason' => 'First comment'
        ]);
    }

    public function test_create_member_badge_stores_badge_award(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);
        $badge = $this->createBadge(['site_id' => $this->siteId]);

        $data = [
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'criteria_met' => [['type' => 'comments_count', 'value' => 10]],
            'is_visible' => true
        ];

        $memberBadge = $this->repository->createMemberBadge($data);

        $this->assertInstanceOf(MemberBadge::class, $memberBadge);
        $this->assertEquals($member->id, $memberBadge->member_id);
        $this->assertEquals($badge->id, $memberBadge->badge_id);
        $this->assertTrue($memberBadge->is_visible);

        $this->assertDatabaseHas('member_badges', [
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'is_visible' => 1
        ]);
    }

    public function test_get_member_activities_since_returns_recent_activities(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);

        $recentActivity = $this->createMemberActivity([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'recent_activity',
            'activity_date' => now_datetime()->modify("-5 day")
        ]);

        $oldActivity = $this->createMemberActivity([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'old_activity',
            'activity_date' => now_datetime()->modify("-40 day")
        ]);

        $startDate = now_datetime()->modify("-30 day")->format('Y-m-d H:i:s');
        $activities = $this->repository->getMemberActivitiesSince($member->id, $startDate);

        $this->assertCount(1, $activities);
        $this->assertCollectionContains($activities, ['id' => $recentActivity->id]);
        $this->assertCollectionDoesNotContain($activities, ['id' => $oldActivity->id]);
    }

    public function test_get_earned_badges_returns_member_badges(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);
        $badge1 = $this->createBadge(['site_id' => $this->siteId, 'name' => 'Badge 1']);
        $badge2 = $this->createBadge(['site_id' => $this->siteId, 'name' => 'Badge 2']);

        $this->createMemberBadge(['member_id' => $member->id, 'badge_id' => $badge1->id]);
        $this->createMemberBadge(['member_id' => $member->id, 'badge_id' => $badge2->id]);

        $earnedBadges = $this->repository->getEarnedBadges($member);

        $this->assertCount(2, $earnedBadges);
        $badgeIds = $earnedBadges->pluck('id')->toArray();
        $this->assertContains($badge1->id, $badgeIds);
        $this->assertContains($badge2->id, $badgeIds);
    }

    public function test_get_comments_count_returns_correct_count(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);

        $this->createComment(['member_id' => $member->id]);
        $this->createComment(['member_id' => $member->id]);
        $this->createComment(['member_id' => $member->id]);

        $count = $this->repository->getCommentsCount($member);

        $this->assertEquals(3, $count);
    }

    public function test_get_distinct_pages_read_returns_unique_page_count(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);
        $page1 = $this->createPage(['site_id' => $this->siteId]);
        $page2 = $this->createPage(['site_id' => $this->siteId]);

        // Multiple views of same pages
        $this->createPageView(['member_id' => $member->id, 'page_id' => $page1->id]);
        $this->createPageView(['member_id' => $member->id, 'page_id' => $page1->id]);
        $this->createPageView(['member_id' => $member->id, 'page_id' => $page2->id]);

        $count = $this->repository->getDistinctPagesRead($member);

        $this->assertEquals(2, $count);
    }

    public function test_get_likes_given_count_returns_correct_count(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);

        $this->createPageLike(['member_id' => $member->id]);
        $this->createPageLike(['member_id' => $member->id]);

        $count = $this->repository->getLikesGivenCount($member);

        $this->assertEquals(2, $count);
    }

    public function test_get_completed_orders_count_returns_only_completed_orders(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);

        $this->createOrder([
            'user_id' => $member->id,
            'status' => 'completed'
        ]);
        $this->createOrder([
            'user_id' => $member->id,
            'status' => 'completed'
        ]);
        $this->createOrder([
            'user_id' => $member->id,
            'status' => 'pending'
        ]);

        $count = $this->repository->getCompletedOrdersCount($member->id);

        $this->assertEquals(2, $count);
    }

    public function test_get_total_spent_calculates_sum_of_completed_orders(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);

        $this->createOrder([
            'user_id' => $member->id,
            'status' => 'completed',
            'total' => 100.00
        ]);
        $this->createOrder([
            'user_id' => $member->id,
            'status' => 'completed',
            'total' => 250.50
        ]);
        $this->createOrder([
            'user_id' => $member->id,
            'status' => 'pending',
            'total' => 75.00
        ]);

        $total = $this->repository->getTotalSpent($member->id);

        $this->assertEquals(350.50, $total);
    }

    public function test_get_total_spent_returns_zero_when_no_orders(): void
    {
        $member = $this->createMember(['site_id' => $this->siteId]);

        $total = $this->repository->getTotalSpent($member->id);

        $this->assertEquals(0, $total);
    }

    public function testGetActiveBadges()
    {
        $this->createBadge(['is_active' => true, 'name' => 'Active 1']);
        $this->createBadge(['is_active' => true, 'name' => 'Active 2']);
        $this->createBadge(['is_active' => false, 'name' => 'Inactive']);

        $badges = $this->repository->getActiveBadges($this->siteId);

        $this->assertCount(2, $badges);
        foreach ($badges as $badge) {
            $this->assertTrue($badge->is_active);
        }
    }

    public function testGetActiveBadgesOrderedBySRetryMortOrder()
    {
        $this->createBadge(['is_active' => true, 'name' => 'Third', 'sort_order' => 3]);
        $this->createBadge(['is_active' => true, 'name' => 'First', 'sort_order' => 1]);
        $this->createBadge(['is_active' => true, 'name' => 'Second', 'sort_order' => 2]);

        $badges = $this->repository->getActiveBadges($this->siteId);

        $this->assertEquals('First', $badges->first()->name);
        $this->assertEquals('Second', $badges->get(1)->name);
        $this->assertEquals('Third', $badges->get(2)->name);
    }

    public function testGetBadgesByCategory()
    {
        $this->createBadge(['category' => 'engagement', 'is_active' => true]);
        $this->createBadge(['category' => 'engagement', 'is_active' => true]);
        $this->createBadge(['category' => 'loyalty', 'is_active' => true]);
        $this->createBadge(['category' => 'engagement', 'is_active' => false]);

        $badges = $this->repository->getBadgesByCategory($this->siteId, 'engagement');

        $this->assertCount(2, $badges);
        foreach ($badges as $badge) {
            $this->assertEquals('engagement', $badge->category);
            $this->assertTrue($badge->is_active);
        }
    }

    public function testFindBySlug()
    {
        $badge = $this->createBadge(['slug' => 'first-comment']);

        $found = Badge::where('slug', 'first-comment')->first();

        $this->assertNotNull($found);
        $this->assertEquals($badge->id, $found->id);
    }

    public function testFiltersBySite()
    {
        $this->createBadge(['site_id' => $this->siteId]);

        $otherSite = $this->createSite();
        $this->createBadge(['site_id' => $otherSite->id]);

        $badges = Badge::where('site_id', $this->siteId)->get();

        $this->assertCount(1, $badges);
        $this->assertEquals($this->siteId, $badges->first()->site_id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BadgeRepository();
    }
}