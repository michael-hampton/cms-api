<?php

namespace App\Tests\Unit\Services;

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

}