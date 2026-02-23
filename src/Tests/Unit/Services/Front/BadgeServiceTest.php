<?php

namespace App\Tests\Unit\Services\Front;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Badge;
use App\Models\Member;
use App\Models\MemberActivity;
use App\Models\MemberBadge;
use App\Models\MemberPoint;
use App\Repositories\Members\BadgeRepository;
use App\Services\Members\BadgeService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class BadgeServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private BadgeRepository $badgeRepository;
    private BadgeService $service;
    private Database $databaseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->badgeRepository = Mockery::mock(BadgeRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->service = new BadgeService($this->badgeRepository, $this->databaseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testTrackActivityCreatesActivityRecord(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = 1;

        $now = new \DateTimeImmutable('2024-01-01 12:00:00');

        $activity = Mockery::mock(MemberActivity::class)->makePartial();
        $activity->id = 1;

        $this->badgeRepository->shouldReceive('createMemberActivity')
            ->once()
            ->with(Mockery::on(function ($data) use ($now) {
                return $data['member_id'] === 1
                    && $data['site_id'] === 1
                    && $data['activity_type'] === 'comment_created'
                    && $data['points'] === 10;
                //&& $data['activity_date'] == $now;
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

    public function testTrackActivityDoesNotAwardPointsWhenZero(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = 1;

        $now = new \DateTimeImmutable();

        $activity = Mockery::mock(MemberActivity::class)->makePartial();

        $this->badgeRepository->shouldReceive('createMemberActivity')
            ->once()
            ->andReturn($activity);

        $this->badgeRepository->shouldNotReceive('createMemberPoint');

        $this->badgeRepository->shouldReceive('getActiveBadgesForSite')
            ->once()
            ->andReturn(collect([]));

        $result = $this->service->trackActivity($member, 'page_view', 'page', 1, [], 0);

        $this->assertInstanceOf(MemberActivity::class, $result);
    }

    public function test_track_activity_passes_correct_payload_to_repository(): void
    {
        $member = $this->makeMember(id: 5, siteId: 10);
        $activity = $this->makeActivity(id: 1);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->badgeRepository
            ->expects('createMemberActivity')
            ->withArgs(function (array $data) {
                return $data['member_id'] === 5
                    && $data['site_id'] === 10
                    && $data['activity_type'] === 'comment'
                    && $data['entity_type'] === 'article'
                    && $data['entity_id'] === 42
                    && $data['points'] === 5;
            })
            ->andReturn($activity);

        $this->badgeRepository->expects('createMemberPoint')->andReturn(new MemberPoint());
        $this->badgeRepository->expects('getActiveBadgesForSite')->andReturn(new Collection([]));

        $this->service->trackActivity($member, 'comment', 'article', 42, [], 5, 10);

        $this->assertTrue(true);
    }

    public function test_track_activity_does_not_award_points_when_zero(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);
        $activity = $this->makeActivity(id: 1);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->badgeRepository->expects('createMemberActivity')->andReturn($activity);
        $this->badgeRepository->shouldNotReceive('createMemberPoint');
        $this->badgeRepository->expects('getActiveBadgesForSite')->andReturn(new Collection([]));

        $this->service->trackActivity($member, 'page_view', points: 0);

        $this->assertTrue(true);
    }

    public function test_track_activity_uses_site_context_when_site_id_not_supplied(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->badgeRepository
            ->expects('createMemberActivity')
            ->withArgs(fn(array $d) => $d['site_id'] === 10)
            ->andReturn($this->makeActivity(id: 1));

        $this->badgeRepository->expects('getActiveBadgesForSite')->andReturn(new Collection([]));

        $this->service->trackActivity($member, 'page_view', null, null, [], 0, 10);

        $this->assertTrue(true);
    }

    public function test_track_activity_uses_explicit_site_id_when_provided(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->badgeRepository
            ->expects('createMemberActivity')
            ->withArgs(fn(array $d) => $d['site_id'] === 99)
            ->andReturn($this->makeActivity(id: 1));

        $this->badgeRepository->expects('getActiveBadgesForSite')->andReturn(new Collection([]));

        $this->service->trackActivity($member, 'page_view', siteId: 99);

        $this->assertTrue(true);
    }

    public function test_track_activity_triggers_badge_check(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);
        $activity = $this->makeActivity(id: 1);
        $badge = $this->makeBadge(id: 1, points: 0, criteriaPass: true);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->badgeRepository->expects('createMemberActivity')->andReturn($activity);
        $this->badgeRepository->expects('getActiveBadgesForSite')->with(10)->andReturn(new Collection([$badge]));
        $this->badgeRepository->expects('findMemberBadge')->with(1, 1)->andReturn(null);
        $this->badgeRepository->expects('createMemberBadge')->once()->andReturn(new MemberBadge());

        $this->service->trackActivity($member, 'page_view');

        $this->assertTrue(true);
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

    public function testCheckAndAwardBadgesAwardsNewBadges(): void
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

    public function testAwardBadgeCreatesRecordAndAwardsPointsWithTransaction(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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
                //&& $data['earned_at'] == $now;
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
                //&& $data['awarded_at'] == $now;
            }))
            ->andReturn(Mockery::mock(MemberPoint::class));

        $result = $this->service->awardBadge($member, $badge);

        $this->assertInstanceOf(MemberBadge::class, $result);
    }

    public function test_check_and_award_badges_returns_empty_when_no_badges(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);

        $this->badgeRepository
            ->expects('getActiveBadgesForSite')
            ->with(10)
            ->andReturn(new Collection([]));

        $result = $this->service->checkAndAwardBadges($member);

        $this->assertSame([], $result);
    }

    public function test_check_and_award_badges_skips_already_earned_badges(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);
        $badge = $this->makeBadge(id: 1, points: 0, criteriaPass: true);
        $memberBadge = new MemberBadge();

        $this->badgeRepository
            ->expects('getActiveBadgesForSite')
            ->andReturn(new Collection([$badge]));

        $this->badgeRepository
            ->expects('findMemberBadge')
            ->with(1, 1)
            ->andReturn($memberBadge);

        $this->badgeRepository->shouldNotReceive('createMemberBadge');

        $result = $this->service->checkAndAwardBadges($member);

        $this->assertSame([], $result);
    }

    public function test_check_and_award_badges_skips_badge_when_criteria_not_met(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);
        $badge = $this->makeBadge(id: 1, points: 0, criteriaPass: false);

        $this->badgeRepository->expects('getActiveBadgesForSite')->andReturn(new Collection([$badge]));
        $this->badgeRepository->expects('findMemberBadge')->andReturn(null);
        $this->badgeRepository->shouldNotReceive('createMemberBadge');

        $result = $this->service->checkAndAwardBadges($member);

        $this->assertSame([], $result);
    }

    public function test_check_and_award_badges_awards_badge_when_criteria_met(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);
        $badge = $this->makeBadge(id: 1, points: 0, criteriaPass: true);
        $memberBadge = new MemberBadge();

        $this->badgeRepository->expects('getActiveBadgesForSite')->andReturn(new Collection([$badge]));
        $this->badgeRepository->expects('findMemberBadge')->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->badgeRepository->expects('createMemberBadge')->once()->andReturn($memberBadge);

        $result = $this->service->checkAndAwardBadges($member);

        $this->assertCount(1, $result);
        $this->assertSame($memberBadge, $result[0]);
    }

    public function test_check_and_award_badges_awards_multiple_badges(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);
        $badge1 = $this->makeBadge(id: 1, points: 0, criteriaPass: true);
        $badge2 = $this->makeBadge(id: 2, points: 0, criteriaPass: true);

        $this->badgeRepository->expects('getActiveBadgesForSite')->andReturn(new Collection([$badge1, $badge2]));
        $this->badgeRepository->expects('findMemberBadge')->twice()->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')->twice()->andReturnUsing(fn(callable $cb) => $cb());
        $this->badgeRepository->expects('createMemberBadge')->twice()->andReturn(new MemberBadge());

        $result = $this->service->checkAndAwardBadges($member);

        $this->assertCount(2, $result);
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->badgeRepository->shouldReceive('createMemberBadge')
            ->once()
            ->andReturn($memberBadge);

        $this->badgeRepository->shouldNotReceive('createMemberPoint');

        $result = $this->service->awardBadge($member, $badge);

        $this->assertInstanceOf(MemberBadge::class, $result);
    }

    public function testGetActivityTrendsReturnsCorrectFormat(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $startDate = new \DateTimeImmutable('2024-01-01');


        $activity1 = Mockery::mock(MemberActivity::class)->makePartial();
        $activity1->points = 10;
        $activity1->activity_date = new \DateTime('2024-01-07');

        $activity2 = Mockery::mock(MemberActivity::class)->makePartial();
        $activity2->points = 20;
        $activity2->activity_date = new \DateTime('2024-01-07');

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

    public function test_award_badge_grants_bonus_points_when_badge_has_points(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);
        $badge = $this->makeBadge(id: 5, points: 100);
        $memberBadge = new MemberBadge();

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->badgeRepository->expects('createMemberBadge')->andReturn($memberBadge);

        $this->badgeRepository
            ->expects('createMemberPoint')
            ->withArgs(fn(array $d) => $d['points'] === 100 && $d['reference_type'] === 'badge')
            ->once()
            ->andReturn(new MemberPoint());

        $this->service->awardBadge($member, $badge);

        $this->assertTrue(true);
    }

    public function test_award_badge_does_not_grant_points_when_badge_has_no_points(): void
    {
        $member = $this->makeMember(id: 1, siteId: 10);
        $badge = $this->makeBadge(id: 5, points: 0);
        $memberBadge = new MemberBadge();

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->badgeRepository->expects('createMemberBadge')->andReturn($memberBadge);
        $this->badgeRepository->shouldNotReceive('createMemberPoint');

        $this->service->awardBadge($member, $badge);

        $this->assertTrue(true);
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->service->awardBadge($member, $badge);

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });


        $this->badgeRepository->shouldReceive('createMemberBadge')
            ->once()
            ->andReturn($memberBadge);

        $memberPoint = Mockery::mock(MemberPoint::class)->makePartial();
        $memberPoint->points = 100;

        $this->badgeRepository->shouldReceive('createMemberPoint')
            ->once()
            ->andReturn($memberPoint);

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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeMember(int $id, int $siteId, int $totalPoints = 0): Member
    {
        $member = new Member();
        $member->id = $id;
        $member->site_id = $siteId;
        $member->totalPoints = $totalPoints;
        $member->activity_stats = [];
        $member->created_at = new \DateTime('-10 days');
        return $member;
    }

    private function makeActivity(int $id, ?string $date = null, int $points = 0): MemberActivity
    {
        $a = new MemberActivity();
        $a->id = $id;
        $a->activity_date = new \DateTime($date ?? 'today');
        $a->points = $points;
        return $a;
    }

    /**
     * @param bool $criteriaPass — controls what badge->checkCriteria() returns
     */
    private function makeBadge(int $id, int $points, bool $criteriaPass = false): Badge
    {
        $badge = Mockery::mock(Badge::class)->makePartial();
        $badge->id = $id;
        $badge->name = "Badge #{$id}";
        $badge->points = $points;
        $badge->site_id = 10;
        $badge->criteria = [];
        $badge->allows('checkCriteria')->andReturn($criteriaPass);
        $badge->allows('contains')->andReturn(false); // for Collection::contains checks
        return $badge;
    }

    private function makeBadgeWithProgress(int $id, bool $criteriaPass, int $progressPct): Badge
    {
        return $this->makeBadge($id, 0, $criteriaPass);
    }

    private function makeBadgeWithCriteria(array $criteria): Badge
    {
        $badge = Mockery::mock(Badge::class)->makePartial();
        $badge->id = 99;
        $badge->name = 'Test Badge';
        $badge->points = 0;
        $badge->criteria = $criteria;
        return $badge;
    }

    /**
     * Minimal event listener hook — replace with your framework's event spy.
     */
    private function listenForEvent(string $eventClass, callable $callback): void
    {
        // If your framework exposes Event::listen() or similar, wire it here.
        // This is a no-op stub so the tests don't fail if the event system
        // isn't available in unit test context.
    }

}