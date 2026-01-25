<?php

namespace App\Tests\Unit\Services\Front;

use App\Models\Badge;
use App\Models\Member;
use App\Models\MemberActivity;
use App\Models\MemberBadge;
use App\Models\MemberPoint;
use App\Repositories\Members\BadgeRepository;
use App\Services\Members\BadgeService;
use App\Services\Rewards\RewardsService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class BadgeServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private BadgeRepository $badgeRepository;
    private BadgeService $service;
    private RewardsService $rewardsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->badgeRepository = Mockery::mock(BadgeRepository::class);
        $this->rewardsService = Mockery::mock(RewardsService::class);
        $this->service = new BadgeService(
            $this->badgeRepository,
            $this->rewardsService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testTrackActivityCreatesActivityRecord()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = 1;

        $activity = Mockery::mock(MemberActivity::class)->makePartial();
        $activity->id = 1;

        $this->badgeRepository->shouldReceive('createMemberActivity')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['member_id'] === 1
                    && $data['site_id'] === 1
                    && $data['activity_type'] === 'comment_created'
                    && $data['points'] === 10;
            }))
            ->andReturn($activity);

        $this->badgeRepository->shouldReceive('createMemberPoint')
            ->once()
            ->andReturn(Mockery::mock(MemberPoint::class));

        $this->badgeRepository->shouldReceive('getActiveBadgesForSite')
            ->once()
            ->andReturn(collect([]));

        $result = $this->service->trackActivity(
            $member,
            'comment_created',
            'comment',
            123,
            ['text' => 'Great post!'],
            10,
            $this->siteId
        );

        $this->assertInstanceOf(MemberActivity::class, $result);
    }

    public function testTrackActivityDoesNotAwardPointsWhenZero()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = 1;

        $activity = Mockery::mock(MemberActivity::class)->makePartial();

        $this->badgeRepository->shouldReceive('createMemberActivity')
            ->once()
            ->andReturn($activity);

        $this->badgeRepository->shouldNotReceive('createMemberPoint');

        $this->badgeRepository->shouldReceive('getActiveBadgesForSite')
            ->once()
            ->andReturn(collect([]));

        $test = $this->service->trackActivity($member, 'page_view', 'page', 1, [], 0);
        $this->assertInstanceOf(MemberActivity::class, $test);
    }

    public function testAwardPointsCreatesPointRecord()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $memberPoint = Mockery::mock(MemberPoint::class)->makePartial();

        $this->badgeRepository->shouldReceive('createMemberPoint')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['member_id'] === 1
                    && $data['points'] === 50
                    && $data['reason'] === 'First post'
                    && $data['reference_type'] === 'post'
                    && $data['reference_id'] === 10;
            }))
            ->andReturn($memberPoint);

        $result = $this->service->awardPoints($member, 50, 'First post', 'post', 10);

        $this->assertInstanceOf(MemberPoint::class, $result);
    }

    public function testCheckAndAwardBadgesSkipsAlreadyEarnedBadges()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = 1;

        $badge = Mockery::mock(Badge::class)->makePartial();
        $badge->id = 1;

        $this->badgeRepository->shouldReceive('getActiveBadgesForSite')
            ->with(1)
            ->once()
            ->andReturn(collect([$badge]));

        $this->badgeRepository->shouldReceive('findMemberBadge')
            ->with(1, 1)
            ->once()
            ->andReturn(Mockery::mock(MemberBadge::class));

        $result = $this->service->checkAndAwardBadges($member);

        $this->assertEmpty($result);
    }

    public function testCheckAndAwardBadgesAwardsNewBadges()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = 1;

        $badge = Mockery::mock(Badge::class)->makePartial();
        $badge->id = 1;
        $badge->name = 'First Comment';
        $badge->points = 100;
        $badge->criteria = [['type' => 'comments_count', 'operator' => '>=', 'value' => 1]];

        $memberBadge = Mockery::mock(MemberBadge::class)->makePartial();

        $this->badgeRepository->shouldReceive('getActiveBadgesForSite')
            ->with(1)
            ->once()
            ->andReturn(collect([$badge]));

        $this->badgeRepository->shouldReceive('findMemberBadge')
            ->with(1, 1)
            ->once()
            ->andReturn(null);

        $badge->shouldReceive('checkCriteria')
            ->with($member)
            ->once()
            ->andReturn(true);

        $this->badgeRepository->shouldReceive('createMemberBadge')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['member_id'] === 1
                    && $data['badge_id'] === 1
                    && $data['is_visible'] === true;
            }))
            ->andReturn($memberBadge);

        $this->badgeRepository->shouldReceive('createMemberPoint')
            ->once()
            ->andReturn(Mockery::mock(MemberPoint::class));

        $result = $this->service->checkAndAwardBadges($member);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(MemberBadge::class, $result[0]);
    }

    public function testCheckAndAwardBadgesDoesNotAwardWhenCriteriaNotMet()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = 1;

        $badge = Mockery::mock(Badge::class)->makePartial();
        $badge->id = 1;

        $this->badgeRepository->shouldReceive('getActiveBadgesForSite')
            ->once()
            ->andReturn(collect([$badge]));

        $this->badgeRepository->shouldReceive('findMemberBadge')
            ->once()
            ->andReturn(null);

        $badge->shouldReceive('checkCriteria')
            ->with($member)
            ->once()
            ->andReturn(false);

        $result = $this->service->checkAndAwardBadges($member);

        $this->assertEmpty($result);
    }

    public function testAwardBadgeCreatesRecordAndAwardsPoints()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = $this->siteId;

        $badge = Mockery::mock(Badge::class)->makePartial();
        $badge->id = 1;
        $badge->name = 'Superstar';
        $badge->points = 200;
        $badge->criteria = [['type' => 'posts_count', 'operator' => '>=', 'value' => 10]];

        $memberBadge = Mockery::mock(MemberBadge::class)->makePartial();

        $this->badgeRepository->shouldReceive('createMemberBadge')
            ->once()
            ->with(Mockery::on(function ($data) use ($badge) {
                return $data['member_id'] === 1
                    && $data['badge_id'] === 1
                    && $data['criteria_met'] === $badge->criteria
                    && $data['is_visible'] === true;
            }))
            ->andReturn($memberBadge);

        $this->badgeRepository->shouldReceive('createMemberPoint')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['member_id'] === 1
                    && $data['points'] === 200
                    && $data['reason'] === 'Badge earned: Superstar'
                    && $data['reference_type'] === 'badge'
                    && $data['reference_id'] === 1;
            }))
            ->andReturn(Mockery::mock(MemberPoint::class));

        $result = $this->service->awardBadge($member, $badge);

        $this->assertInstanceOf(MemberBadge::class, $result);
    }

    public function testAwardBadgeDoesNotAwardPointsWhenBadgeHasZeroPoints()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = $this->siteId;

        $badge = Mockery::mock(Badge::class)->makePartial();
        $badge->id = 1;
        $badge->name = 'Participation';
        $badge->points = 0;
        $badge->criteria = [];

        $memberBadge = Mockery::mock(MemberBadge::class)->makePartial();

        $this->badgeRepository->shouldReceive('createMemberBadge')
            ->once()
            ->andReturn($memberBadge);

        $this->badgeRepository->shouldNotReceive('createMemberPoint');

        $result = $this->service->awardBadge($member, $badge);

        $this->assertInstanceOf(MemberBadge::class, $result);
    }

    public function testGetActivityTrendsReturnsCorrectFormat()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $activity1 = Mockery::mock(MemberActivity::class)->makePartial();
        $activity1->points = 10;
        $activity1->activity_date = now_datetime()->format('Y-m-d');

        $activity2 = Mockery::mock(MemberActivity::class)->makePartial();
        $activity2->points = 20;
        $activity2->activity_date = now_datetime()->format('Y-m-d');

        $this->badgeRepository->shouldReceive('getMemberActivitiesSince')
            ->once()
            ->andReturn(collect([$activity1, $activity2]));

        $result = $this->service->getActivityTrends($member, 7);

        $this->assertIsArray($result);
        $this->assertCount(7, $result);
        $this->assertArrayHasKey('date', $result[0]);
        $this->assertArrayHasKey('count', $result[0]);
        $this->assertArrayHasKey('points', $result[0]);
    }

    public function testGetMemberProgressReturnsCompleteData()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = 1;
        $member->total_points = 500;
        $member->activity_stats = ['total_activities' => 50];
        $member->created_at = now_datetime()->subDays(30)->format('Y-m-d');

        $earnedBadge = Mockery::mock(Badge::class)->makePartial();
        $earnedBadge->id = 1;

        $member->shouldReceive('getTotalPointsAttribute')
            ->once()
            ->andReturn(500);

        $availableBadge = Mockery::mock(Badge::class)->makePartial();
        $availableBadge->id = 2;
        $availableBadge->criteria = [
            ['type' => 'comments_count', 'operator' => '>=', 'value' => 10]
        ];

        $this->badgeRepository->shouldReceive('getEarnedBadges')
            ->with($member)
            ->once()
            ->andReturn(collect([$earnedBadge]));

        $this->badgeRepository->shouldReceive('getActiveBadgesForSite')
            ->with(1)
            ->once()
            ->andReturn(collect([$earnedBadge, $availableBadge]));

        $this->badgeRepository->shouldReceive('getCommentsCount')
            ->with($member)
            ->once()
            ->andReturn(5);

        $result = $this->service->getMemberProgress($member, $this->siteId);

        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('total_points', $result);
        $this->assertArrayHasKey('badges_earned', $result);
        $this->assertArrayHasKey('badges_available', $result);
        $this->assertArrayHasKey('next_badges', $result);
        $this->assertEquals(500, $result['total_points']);
        $this->assertEquals(1, $result['badges_earned']);
        $this->assertEquals(2, $result['badges_available']);
    }

    public function testGetMemberProgressLimitsNextBadgesToFive()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = 1;
        $member->total_points = 100;
        $member->activity_stats = [];
        $member->created_at = now();

        $badges = collect();
        for ($i = 0; $i < 10; $i++) {
            $badge = Mockery::mock(Badge::class)->makePartial();
            $badge->id = $i;
            $badge->criteria = [['type' => 'comments_count', 'operator' => '>=', 'value' => 1]];
            $badges->push($badge);
        }

        $this->badgeRepository->shouldReceive('getEarnedBadges')
            ->once()
            ->andReturn(collect([]));

        $this->badgeRepository->shouldReceive('getActiveBadgesForSite')
            ->once()
            ->andReturn($badges);

        $this->badgeRepository->shouldReceive('getCommentsCount')
            ->times(10)
            ->andReturn(1);

        $result = $this->service->getMemberProgress($member, $this->siteId);;

        $this->assertCount(5, $result['next_badges']);
    }

    public function testAwardBadgeSetsSessionFlag(): void
    {
        $member = $this->createMember();

        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Test Badge',
            'slug' => 'test-badge',
            'description' => 'Test Description',
            'icon' => '🏆',
            'points' => 10,
            'criteria' => [],
            'is_active' => true,
            'category' => 'test'
        ]);

        $this->badgeRepository->shouldReceive('createMemberBadge')
            ->once()
            ->andReturn(Mockery::mock(\App\Models\MemberBadge::class)->makePartial());

        $this->badgeRepository->shouldReceive('createMemberPoint')
            ->once()
            ->andReturn(Mockery::mock(\App\Models\MemberPoint::class)->makePartial());

        $this->rewardsService->shouldReceive('checkAndAwardRewards')
            ->once()
            ->andReturn([]);

        $memberBadge = $this->service->awardBadge($member, $badge);

        $this->assertArrayHasKey('show_badge_modal', $_SESSION);
        $this->assertArrayHasKey('new_badge_data', $_SESSION);
    }

    public function testAwardBadgeSetsSessionForModal(): void
    {
        $member = $this->createMember();
        $memberBadge = Mockery::mock(MemberBadge::class);

        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Test Badge',
            'slug' => 'test-badge',
            'description' => 'Test Description',
            'icon' => '🏆',
            'points' => 100,
            'criteria' => [],
            'is_active' => true,
            'category' => 'test'
        ]);

        $this->badgeRepository->shouldReceive('createMemberBadge')
            ->once()
            ->andReturn($memberBadge);

        $memberPoint = Mockery::mock(MemberPoint::class)->makePartial();
        $memberPoint->points = 100;

        $this->badgeRepository->shouldReceive('createMemberPoint')
            ->once()
            ->andReturn($memberPoint);

        $this->rewardsService->shouldReceive('checkAndAwardRewards')
            ->once()
            ->andReturn([]);

        $this->service->awardBadge($member, $badge);

        $this->assertTrue($_SESSION['show_badge_modal']);
        $this->assertArrayHasKey('new_badge_data', $_SESSION);
        $this->assertEquals('Test Badge', $_SESSION['new_badge_data']['name']);
        $this->assertEquals('Test Description', $_SESSION['new_badge_data']['description']);
        $this->assertEquals('🏆', $_SESSION['new_badge_data']['icon']);
        $this->assertEquals(100, $_SESSION['new_badge_data']['points']);
    }

    public function testCalculateBadgeProgressReturnsCorrectPercentage(): void
    {
        $member = $this->createMember();

        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Comment Badge',
            'slug' => 'comment-badge',
            'description' => 'Post 10 comments',
            'icon' => '💬',
            'points' => 100,
            'criteria' => [
                ['type' => 'comments_count', 'operator' => '>=', 'value' => 10]
            ],
            'is_active' => true,
            'category' => 'engagement'
        ]);

        $this->badgeRepository->shouldReceive('getCommentsCount')
            ->with($member)
            ->once()
            ->andReturn(5);

        $progress = $this->service->calculateBadgeProgress($member, $badge);

        $this->assertEquals(0, $progress['percentage']); // 0% because criteria not met
        $this->assertEquals(0, $progress['met']);
        $this->assertEquals(1, $progress['total']);
        $this->assertCount(1, $progress['details']);
        $this->assertEquals(5, $progress['details'][0]['current']);
        $this->assertEquals(10, $progress['details'][0]['target']);
        $this->assertFalse($progress['details'][0]['met']);
        $this->assertEquals(50, $progress['details'][0]['percentage']); // 5/10 = 50%
    }

    public function testCalculateBadgeProgressWithMultipleCriteria(): void
    {
        $member = $this->createMember();

        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Super Engager',
            'slug' => 'super-engager',
            'description' => 'Post comments and give likes',
            'icon' => '⭐',
            'points' => 200,
            'criteria' => [
                ['type' => 'comments_count', 'operator' => '>=', 'value' => 10],
                ['type' => 'likes_given', 'operator' => '>=', 'value' => 20]
            ],
            'is_active' => true,
            'category' => 'engagement'
        ]);

        $this->badgeRepository->shouldReceive('getCommentsCount')
            ->with($member)
            ->once()
            ->andReturn(10);

        $this->badgeRepository->shouldReceive('getLikesGivenCount')
            ->with($member)
            ->once()
            ->andReturn(15);

        $progress = $this->service->calculateBadgeProgress($member, $badge);

        $this->assertEquals(50, $progress['percentage']); // 1 of 2 criteria met = 50%
        $this->assertEquals(1, $progress['met']);
        $this->assertEquals(2, $progress['total']);
        $this->assertCount(2, $progress['details']);

        // First criteria (comments) - met
        $this->assertEquals(10, $progress['details'][0]['current']);
        $this->assertEquals(10, $progress['details'][0]['target']);
        $this->assertTrue($progress['details'][0]['met']);
        $this->assertEquals(100, $progress['details'][0]['percentage']);

        // Second criteria (likes) - not met
        $this->assertEquals(15, $progress['details'][1]['current']);
        $this->assertEquals(20, $progress['details'][1]['target']);
        $this->assertFalse($progress['details'][1]['met']);
        $this->assertEquals(75, $progress['details'][1]['percentage']); // 15/20 = 75%
    }

    public function testCalculateBadgeProgressWhenAllCriteriaMet(): void
    {
        $member = $this->createMember();

        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Complete Badge',
            'slug' => 'complete-badge',
            'description' => 'Complete all tasks',
            'icon' => '🏆',
            'points' => 500,
            'criteria' => [
                ['type' => 'comments_count', 'operator' => '>=', 'value' => 5],
                ['type' => 'pages_read', 'operator' => '>=', 'value' => 10]
            ],
            'is_active' => true,
            'category' => 'achievement'
        ]);

        $this->badgeRepository->shouldReceive('getCommentsCount')
            ->with($member)
            ->once()
            ->andReturn(10);

        $this->badgeRepository->shouldReceive('getDistinctPagesRead')
            ->with($member)
            ->once()
            ->andReturn(15);

        $progress = $this->service->calculateBadgeProgress($member, $badge);

        $this->assertEquals(100, $progress['percentage']); // 2 of 2 criteria met = 100%
        $this->assertEquals(2, $progress['met']);
        $this->assertEquals(2, $progress['total']);
        $this->assertTrue($progress['details'][0]['met']);
        $this->assertTrue($progress['details'][1]['met']);
    }

    public function testCalculateBadgeProgressWithMemberDaysCriteria(): void
    {
        $member = $this->createMember();
        $member->created_at = now_datetime()->subDays(15)->format('Y-m-d H:i:s');

        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Veteran',
            'slug' => 'veteran',
            'description' => 'Member for 30 days',
            'icon' => '🎖️',
            'points' => 100,
            'criteria' => [
                ['type' => 'member_days', 'operator' => '>=', 'value' => 30]
            ],
            'is_active' => true,
            'category' => 'loyalty'
        ]);

        $progress = $this->service->calculateBadgeProgress($member, $badge);

        $this->assertEquals(0, $progress['percentage']); // Criteria not met
        $this->assertEquals(0, $progress['met']);
        $this->assertEquals(1, $progress['total']);
        $this->assertEquals(15, $progress['details'][0]['current']);
        $this->assertEquals(30, $progress['details'][0]['target']);
        $this->assertFalse($progress['details'][0]['met']);
        $this->assertEquals(50, $progress['details'][0]['percentage']); // 15/30 = 50%
    }

    public function testCalculateBadgeProgressWithOrdersCriteria(): void
    {
        $member = $this->createMember();

        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Frequent Buyer',
            'slug' => 'frequent-buyer',
            'description' => 'Make 5 purchases',
            'icon' => '🛒',
            'points' => 150,
            'criteria' => [
                ['type' => 'orders_count', 'operator' => '>=', 'value' => 5]
            ],
            'is_active' => true,
            'category' => 'commerce'
        ]);

        $this->badgeRepository->shouldReceive('getCompletedOrdersCount')
            ->with($member->id)
            ->once()
            ->andReturn(3);

        $progress = $this->service->calculateBadgeProgress($member, $badge);

        $this->assertEquals(0, $progress['percentage']);
        $this->assertEquals(0, $progress['met']);
        $this->assertEquals(3, $progress['details'][0]['current']);
        $this->assertEquals(5, $progress['details'][0]['target']);
        $this->assertEquals(60, $progress['details'][0]['percentage']); // 3/5 = 60%
    }

    public function testCalculateBadgeProgressWithTotalSpentCriteria(): void
    {
        $member = $this->createMember();

        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Big Spender',
            'slug' => 'big-spender',
            'description' => 'Spend £500',
            'icon' => '💰',
            'points' => 300,
            'criteria' => [
                ['type' => 'total_spent', 'operator' => '>=', 'value' => 500]
            ],
            'is_active' => true,
            'category' => 'commerce'
        ]);

        $this->badgeRepository->shouldReceive('getTotalSpent')
            ->with($member->id)
            ->once()
            ->andReturn(350.00);

        $progress = $this->service->calculateBadgeProgress($member, $badge);

        $this->assertEquals(0, $progress['percentage']);
        $this->assertEquals(0, $progress['met']);
        $this->assertEquals(350.00, $progress['details'][0]['current']);
        $this->assertEquals(500, $progress['details'][0]['target']);
        $this->assertEquals(70, $progress['details'][0]['percentage']); // 350/500 = 70%
    }

    public function testCalculateBadgeProgressWithZeroTarget(): void
    {
        $member = $this->createMember();

        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Edge Case Badge',
            'slug' => 'edge-case',
            'description' => 'Edge case test',
            'icon' => '🔧',
            'points' => 10,
            'criteria' => [
                ['type' => 'comments_count', 'operator' => '>=', 'value' => 0]
            ],
            'is_active' => true,
            'category' => 'test'
        ]);

        $this->badgeRepository->shouldReceive('getCommentsCount')
            ->with($member)
            ->once()
            ->andReturn(5);

        $progress = $this->service->calculateBadgeProgress($member, $badge);

        $this->assertEquals(100, $progress['percentage']); // Criteria met (5 >= 0)
        $this->assertEquals(1, $progress['met']);
        $this->assertEquals(0, $progress['details'][0]['percentage']); // Zero target means 0% progress display
    }

    public function testCalculateBadgeProgressWithDifferentOperators(): void
    {
        $member = $this->createMember();

        // Test with "less than" operator
        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Minimal Badge',
            'slug' => 'minimal',
            'description' => 'Keep it minimal',
            'icon' => '📉',
            'points' => 50,
            'criteria' => [
                ['type' => 'comments_count', 'operator' => '<', 'value' => 5]
            ],
            'is_active' => true,
            'category' => 'special'
        ]);

        $this->badgeRepository->shouldReceive('getCommentsCount')
            ->with($member)
            ->once()
            ->andReturn(3);

        $progress = $this->service->calculateBadgeProgress($member, $badge);

        $this->assertEquals(100, $progress['percentage']); // Criteria met (3 < 5)
        $this->assertEquals(1, $progress['met']);
        $this->assertTrue($progress['details'][0]['met']);
    }

    public function testCalculateBadgeProgressWithPagesReadCriteria(): void
    {
        $member = $this->createMember();

        $badge = \App\Models\Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Avid Reader',
            'slug' => 'avid-reader',
            'description' => 'Read 50 different pages',
            'icon' => '📚',
            'points' => 200,
            'criteria' => [
                ['type' => 'pages_read', 'operator' => '>=', 'value' => 50]
            ],
            'is_active' => true,
            'category' => 'reading'
        ]);

        $this->badgeRepository->shouldReceive('getDistinctPagesRead')
            ->with($member)
            ->once()
            ->andReturn(30);

        $progress = $this->service->calculateBadgeProgress($member, $badge);

        $this->assertEquals(0, $progress['percentage']);
        $this->assertEquals(0, $progress['met']);
        $this->assertEquals(30, $progress['details'][0]['current']);
        $this->assertEquals(50, $progress['details'][0]['target']);
        $this->assertEquals(60, $progress['details'][0]['percentage']); // 30/50 = 60%
    }

}