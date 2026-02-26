<?php

namespace App\Tests\Unit\Services\Quiz;

use App\Exceptions\CompetitionAlreadyEnteredException;
use App\Exceptions\CompetitionEntryNotUnlockedException;
use App\Exceptions\CompetitionNotAvailableException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionNotification;
use App\Models\Member;
use App\Models\MemberActivity;
use App\Repositories\Members\BadgeRepository;
use App\Repositories\Quiz\CompetitionRepository;
use App\Services\Members\BadgeService;
use App\Services\Quiz\CompetitionService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class CompetitionServiceTest extends FunctionalTestCase
{
    private CompetitionRepository|MockInterface $competitionRepository;
    private BadgeRepository|MockInterface $badgeRepository;
    private BadgeService|MockInterface $badgeService;
    private Database|MockInterface $databaseMock;
    private CompetitionService $service;

    // -------------------------------------------------------------------------
    // getCompetitionsForSite
    // -------------------------------------------------------------------------

    public function test_get_competitions_for_site_returns_empty_when_none(): void
    {
        $this->competitionRepository
            ->expects('getActiveForSite')
            ->with(1)
            ->andReturn(new Collection([]));

        $result = $this->service->getCompetitionsForSite(1);

        $this->assertSame([], $result);
    }

    public function test_get_competitions_for_site_decorates_each_competition(): void
    {
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'open');

        $this->competitionRepository->expects('getActiveForSite')->andReturn(new Collection([$competition]));
        $this->competitionRepository->expects('getEntryCount')->andReturn(42);

        $result = $this->service->getCompetitionsForSite(1, member: null);

        $this->assertCount(1, $result);
        $this->assertSame(10, $result[0]['id']);
        $this->assertSame(42, $result[0]['entry_count']);
    }

    private function makeCompetition(
        int        $id,
        bool       $isActive = true,
        bool       $isComingSoon = false,
        bool       $hasEnded = false,
        string     $entryType = 'open',
        array      $criteria = [],
        ?string    $externalUrl = null,
        ?\DateTime $startsAt = null,
        ?\DateTime $endsAt = null,
    ): Competition
    {
        $c = Mockery::mock(Competition::class)->makePartial();
        $c->id = $id;
        $c->title = "Competition #{$id}";
        $c->description = 'Test description';
        $c->slug = "comp-{$id}";
        $c->entry_type = $entryType;
        $c->prize_description = 'A prize';
        $c->starts_at = $startsAt;
        $c->ends_at = $endsAt;
        $c->settings = array_filter([
            'entry_criteria' => $criteria,
            'external_url' => $externalUrl,
        ]);

        $c->allows('isActive')->andReturn($isActive);
        $c->allows('isComingSoon')->andReturn($isComingSoon);
        $c->allows('hasEnded')->andReturn($hasEnded);
        $c->allows('getEntryCriteria')->andReturn($criteria);
        $c->allows('getExternalUrl')->andReturn($externalUrl);

        return $c;
    }

    public function test_get_competitions_for_site_does_not_check_entry_when_no_member(): void
    {
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'open');

        $this->competitionRepository->expects('getActiveForSite')->andReturn(new Collection([$competition]));
        $this->competitionRepository->expects('getEntryCount')->andReturn(0);
        $this->competitionRepository->shouldNotReceive('findEntry');
        $this->competitionRepository->shouldNotReceive('findNotification');

        $result = $this->service->getCompetitionsForSite(1, member: null);

        $this->assertFalse($result[0]['has_entered']);
        $this->assertNull($result[0]['progress']);
    }

    public function test_get_competitions_for_site_marks_member_as_entered_when_entry_exists(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'open');

        $this->competitionRepository->expects('getActiveForSite')->andReturn(new Collection([$competition]));
        $this->competitionRepository->expects('findEntry')->with(10, 5)->andReturn(new CompetitionEntry());
        $this->competitionRepository->expects('findNotification')->andReturn(null);
        $this->competitionRepository->expects('getEntryCount')->andReturn(1);

        $result = $this->service->getCompetitionsForSite(1, $member);

        $this->assertTrue($result[0]['has_entered']);
    }

    private function makeMember(int $id): Member
    {
        $m = new Member();
        $m->id = $id;
        return $m;
    }

    public function test_get_competitions_for_site_marks_notification_when_set(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'open');

        $this->competitionRepository->expects('getActiveForSite')->andReturn(new Collection([$competition]));
        $this->competitionRepository->expects('findEntry')->andReturn(null);
        $this->competitionRepository->expects('findNotification')->with(10, 5)->andReturn(new CompetitionNotification());
        $this->competitionRepository->expects('getEntryCount')->andReturn(0);

        $result = $this->service->getCompetitionsForSite(1, $member);

        $this->assertTrue($result[0]['has_notification']);
    }

    public function test_get_competitions_for_site_formats_dates(): void
    {
        $competition = $this->makeCompetition(
            id: 10, isActive: true, entryType: 'open',
            startsAt: new \DateTime('2025-03-01'),
            endsAt: new \DateTime('2025-12-25'),
        );

        $this->competitionRepository->expects('getActiveForSite')->andReturn(new Collection([$competition]));
        $this->competitionRepository->expects('getEntryCount')->andReturn(0);

        $result = $this->service->getCompetitionsForSite(1, member: null);

        $this->assertSame('Mar 1', $result[0]['starts_at']);
        $this->assertSame('Dec 25', $result[0]['ends_at']);
    }

    public function test_get_competitions_for_site_returns_null_dates_when_not_set(): void
    {
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'open');

        $this->competitionRepository->expects('getActiveForSite')->andReturn(new Collection([$competition]));
        $this->competitionRepository->expects('getEntryCount')->andReturn(0);

        $result = $this->service->getCompetitionsForSite(1, member: null);

        $this->assertNull($result[0]['starts_at']);
        $this->assertNull($result[0]['ends_at']);
    }

    public function test_get_competitions_for_site_returns_external_url_for_sponsored(): void
    {
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'sponsored', externalUrl: 'https://samsung.com/promo');

        $this->competitionRepository->expects('getActiveForSite')->andReturn(new Collection([$competition]));
        $this->competitionRepository->expects('getEntryCount')->andReturn(0);

        $result = $this->service->getCompetitionsForSite(1, member: null);

        $this->assertSame('https://samsung.com/promo', $result[0]['external_url']);
    }

    // -------------------------------------------------------------------------
    // enter — guards
    // -------------------------------------------------------------------------

    public function test_get_competitions_for_site_shows_coming_soon_status(): void
    {
        $competition = $this->makeCompetition(id: 10, isActive: false, isComingSoon: true, entryType: 'open');

        $this->competitionRepository->expects('getActiveForSite')->andReturn(new Collection([$competition]));
        $this->competitionRepository->expects('getEntryCount')->andReturn(0);

        $result = $this->service->getCompetitionsForSite(1, member: null);

        $this->assertSame('coming_soon', $result[0]['status']);
    }

    public function test_get_competitions_for_site_shows_ended_status(): void
    {
        $competition = $this->makeCompetition(id: 10, isActive: false, hasEnded: true, entryType: 'open');

        $this->competitionRepository->expects('getActiveForSite')->andReturn(new Collection([$competition]));
        $this->competitionRepository->expects('getEntryCount')->andReturn(0);

        $result = $this->service->getCompetitionsForSite(1, member: null);

        $this->assertSame('ended', $result[0]['status']);
    }

    public function test_enter_throws_when_competition_not_found(): void
    {
        $member = $this->makeMember(id: 5);

        $this->competitionRepository->expects('find')->with(10)->andReturn(null);

        $this->expectException(CompetitionNotAvailableException::class);

        $this->service->enter(10, $member);
    }

    public function test_enter_throws_when_competition_not_active(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: false, entryType: 'open');

        $this->competitionRepository->expects('find')->with(10)->andReturn($competition);

        $this->expectException(CompetitionNotAvailableException::class);

        $this->service->enter(10, $member);
    }

    // -------------------------------------------------------------------------
    // enter — happy path
    // -------------------------------------------------------------------------

    public function test_enter_throws_when_member_already_entered(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'open');

        $this->competitionRepository->expects('find')->andReturn($competition);
        $this->competitionRepository->expects('findEntry')->with(10, 5)->andReturn(new CompetitionEntry());

        $this->expectException(CompetitionAlreadyEnteredException::class);

        $this->service->enter(10, $member);
    }

    public function test_enter_throws_when_entry_criteria_not_met(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'badge', criteria: [
            ['type' => 'badge_count', 'value' => 3],
        ]);

        $this->competitionRepository->expects('find')->andReturn($competition);
        $this->competitionRepository->expects('findEntry')->andReturn(null);

        // Member has 0 badges
        $this->badgeRepository->expects('getEarnedBadges')->andReturn(new Collection([]));

        $this->expectException(CompetitionEntryNotUnlockedException::class);

        $this->service->enter(10, $member);
    }

    public function test_enter_creates_entry_for_open_competition(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'open', criteria: []);
        $entry = new CompetitionEntry();

        $this->competitionRepository->expects('find')->andReturn($competition);
        $this->competitionRepository->expects('findEntry')->andReturn(null);
        $this->competitionRepository->expects('createEntry')->once()->andReturn($entry);
        $this->competitionRepository->expects('getEntryCount')->andReturn(1);

        $this->badgeService->expects('trackActivity')->once();

        $this->databaseMock->expects('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $result = $this->service->enter(10, $member);

        $this->assertSame($entry, $result['entry']);
        $this->assertSame(1, $result['entry_count']);
    }

    public function test_enter_runs_inside_transaction(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'open', criteria: []);
        $called = false;

        $this->competitionRepository->expects('find')->andReturn($competition);
        $this->competitionRepository->expects('findEntry')->andReturn(null);
        $this->competitionRepository->expects('createEntry')->andReturn(new CompetitionEntry());
        $this->competitionRepository->expects('getEntryCount')->andReturn(0);
        $this->badgeService->expects('trackActivity');

        $this->databaseMock
            ->expects('transaction')
            ->once()
            ->andReturnUsing(function (callable $cb) use (&$called) {
                $called = true;
                return $cb();
            });

        $this->service->enter(10, $member);

        $this->assertTrue($called);
    }

    // -------------------------------------------------------------------------
    // requestNotification
    // -------------------------------------------------------------------------

    public function test_enter_stores_referred_by_member_id(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'referral', criteria: []);

        $this->competitionRepository->expects('find')->andReturn($competition);
        $this->competitionRepository->expects('findEntry')->andReturn(null);
        $this->competitionRepository
            ->expects('createEntry')
            ->withArgs(fn(array $d) => $d['referred_by_member_id'] === 99)
            ->andReturn(new CompetitionEntry());
        $this->competitionRepository->expects('getEntryCount')->andReturn(1);
        $this->badgeService->expects('trackActivity');

        $this->databaseMock->expects('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->service->enter(10, $member, referredByMemberId: 99);

        $this->assertTrue(true);
    }

    public function test_enter_tracks_competition_entry_activity(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'open', criteria: []);

        $this->competitionRepository->expects('find')->andReturn($competition);
        $this->competitionRepository->expects('findEntry')->andReturn(null);
        $this->competitionRepository->expects('createEntry')->andReturn(new CompetitionEntry());
        $this->competitionRepository->expects('getEntryCount')->andReturn(1);

        $this->badgeService
            ->expects('trackActivity')
            ->withArgs(fn($m, string $type) => $type === 'competition_entry')
            ->once();

        $this->databaseMock->expects('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->service->enter(10, $member);

        $this->assertTrue(true);
    }

    public function test_request_notification_throws_when_competition_not_found(): void
    {
        $member = $this->makeMember(id: 5);
        $this->competitionRepository->expects('find')->with(10)->andReturn(null);

        $this->expectException(CompetitionNotAvailableException::class);

        $this->service->requestNotification(10, $member);
    }

    // -------------------------------------------------------------------------
    // getEntryProgress — open competition
    // -------------------------------------------------------------------------

    public function test_request_notification_creates_notification(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: false, entryType: 'open');

        $this->competitionRepository->expects('find')->andReturn($competition);
        $this->competitionRepository->expects('findNotification')->with(10, 5)->andReturn(null);
        $this->competitionRepository->expects('createNotification')->once()->andReturn(new CompetitionNotification());

        $result = $this->service->requestNotification(10, $member);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // getEntryProgress — badge_count criterion
    // -------------------------------------------------------------------------

    public function test_request_notification_is_idempotent_when_already_registered(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: false, entryType: 'open');

        $this->competitionRepository->expects('find')->andReturn($competition);
        $this->competitionRepository->expects('findNotification')->andReturn(new CompetitionNotification());
        $this->competitionRepository->shouldNotReceive('createNotification');

        $result = $this->service->requestNotification(10, $member);

        $this->assertTrue($result);
    }

    public function test_get_entry_progress_returns_unlocked_for_open_competition(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'open', criteria: []);

        $progress = $this->service->getEntryProgress($competition, $member);

        $this->assertTrue($progress['unlocked']);
        $this->assertSame(100, $progress['percentage']);
    }

    // -------------------------------------------------------------------------
    // getEntryProgress — badge_ids criterion
    // -------------------------------------------------------------------------

    public function test_get_entry_progress_badge_count_met(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'badge', criteria: [
            ['type' => 'badge_count', 'value' => 2],
        ]);

        $this->badgeRepository
            ->expects('getEarnedBadges')
            ->andReturn(new Collection([new \App\Models\MemberBadge(), new \App\Models\MemberBadge()]));

        $progress = $this->service->getEntryProgress($competition, $member);

        $this->assertTrue($progress['unlocked']);
        $this->assertSame(100, $progress['percentage']);
    }

    public function test_get_entry_progress_badge_count_not_met(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'badge', criteria: [
            ['type' => 'badge_count', 'value' => 5],
        ]);

        $this->badgeRepository
            ->expects('getEarnedBadges')
            ->andReturn(new Collection([new \App\Models\MemberBadge()]));

        $progress = $this->service->getEntryProgress($competition, $member);

        $this->assertFalse($progress['unlocked']);
        $this->assertSame(20, $progress['percentage']); // 1/5
    }

    // -------------------------------------------------------------------------
    // getEntryProgress — return_visits criterion
    // -------------------------------------------------------------------------

    public function test_get_entry_progress_badge_ids_all_earned(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'badge', criteria: [
            ['type' => 'badge_ids', 'badge_ids' => [1, 2]],
        ]);

        $b1 = new \App\Models\MemberBadge();
        $b1->badge_id = 1;
        $b2 = new \App\Models\MemberBadge();
        $b2->badge_id = 2;

        $this->badgeRepository->expects('getEarnedBadges')->andReturn(new Collection([$b1, $b2]));

        $progress = $this->service->getEntryProgress($competition, $member);

        $this->assertTrue($progress['unlocked']);
    }

    public function test_get_entry_progress_badge_ids_partially_earned(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'badge', criteria: [
            ['type' => 'badge_ids', 'badge_ids' => [1, 2, 3]],
        ]);

        $b1 = new \App\Models\MemberBadge();
        $b1->badge_id = 1;

        $this->badgeRepository->expects('getEarnedBadges')->andReturn(new Collection([$b1]));

        $progress = $this->service->getEntryProgress($competition, $member);
        $detail = $progress['details'][0];

        $this->assertFalse($progress['unlocked']);
        $this->assertSame(1, $detail['current']);
        $this->assertSame(3, $detail['target']);
    }

    public function test_get_entry_progress_return_visits_met_with_action_requirement(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'activity', criteria: [
            [
                'type' => 'return_visits',
                'visits' => 3,
                'actions_per_visit' => 2,
                'action_types' => ['comment', 'article_read'],
                'within_days' => 30,
            ],
        ]);

        // 3 qualifying days, each with 2+ matching actions
        $activities = [];
        foreach (['2025-06-01', '2025-06-02', '2025-06-03'] as $date) {
            foreach (['comment', 'article_read'] as $type) {
                $a = new MemberActivity();
                $a->activity_date = new \DateTime($date);
                $a->activity_type = $type;
                $activities[] = $a;
            }
        }

        $this->badgeRepository->expects('getMemberActivitiesSince')->andReturn(new Collection($activities));

        $progress = $this->service->getEntryProgress($competition, $member);

        $this->assertTrue($progress['unlocked']);
        $this->assertSame(3, $progress['details'][0]['current']);
    }

    // -------------------------------------------------------------------------
    // getEntryProgress — multiple criteria
    // -------------------------------------------------------------------------

    public function test_get_entry_progress_return_visits_not_met_when_actions_insufficient(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'activity', criteria: [
            [
                'type' => 'return_visits',
                'visits' => 3,
                'actions_per_visit' => 3,
                'action_types' => ['comment', 'article_read', 'game_play'],
                'within_days' => 30,
            ],
        ]);

        // Only 1 qualifying day (others have fewer than 3 matching actions)
        $activities = [
            $this->makeActivity('2025-06-01', 'comment'),
            $this->makeActivity('2025-06-01', 'article_read'),
            $this->makeActivity('2025-06-01', 'game_play'),    // day 1: qualifies
            $this->makeActivity('2025-06-02', 'comment'),      // day 2: only 1 action
        ];

        $this->badgeRepository->expects('getMemberActivitiesSince')->andReturn(new Collection($activities));

        $progress = $this->service->getEntryProgress($competition, $member);

        $this->assertFalse($progress['unlocked']);
        $this->assertSame(1, $progress['details'][0]['current']);
    }

    private function makeActivity(string $date, string $type): MemberActivity
    {
        $a = new MemberActivity();
        $a->activity_date = new \DateTime($date);
        $a->activity_type = $type;
        return $a;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_get_entry_progress_return_visits_without_action_type_filter(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'activity', criteria: [
            [
                'type' => 'return_visits',
                'visits' => 2,
                'actions_per_visit' => 1,
                'action_types' => [],   // no filter — any activity counts
                'within_days' => 30,
            ],
        ]);

        $activities = [
            $this->makeActivity('2025-06-01', 'anything'),
            $this->makeActivity('2025-06-02', 'anything'),
        ];

        $this->badgeRepository->expects('getMemberActivitiesSince')->andReturn(new Collection($activities));

        $progress = $this->service->getEntryProgress($competition, $member);

        $this->assertTrue($progress['unlocked']);
    }

    public function test_get_entry_progress_all_criteria_must_be_met_for_unlocked(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'badge', criteria: [
            ['type' => 'badge_count', 'value' => 2],
            ['type' => 'badge_count', 'value' => 5], // will not be met
        ]);

        $this->badgeRepository
            ->expects('getEarnedBadges')
            ->twice()
            ->andReturn(new Collection([new \App\Models\MemberBadge(), new \App\Models\MemberBadge()]));

        $progress = $this->service->getEntryProgress($competition, $member);

        $this->assertFalse($progress['unlocked']);
        $this->assertSame(1, $progress['met']);
        $this->assertSame(2, $progress['total']);
    }

    public function test_get_entry_progress_percentage_reflects_partial_completion(): void
    {
        $member = $this->makeMember(id: 5);
        $competition = $this->makeCompetition(id: 10, isActive: true, entryType: 'badge', criteria: [
            ['type' => 'badge_count', 'value' => 1],  // met
            ['type' => 'badge_count', 'value' => 100], // not met
        ]);

        $this->badgeRepository
            ->expects('getEarnedBadges')
            ->twice()
            ->andReturn(new Collection([new \App\Models\MemberBadge()]));

        $progress = $this->service->getEntryProgress($competition, $member);

        $this->assertSame(51, $progress['percentage']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->competitionRepository = Mockery::mock(CompetitionRepository::class);
        $this->badgeRepository = Mockery::mock(BadgeRepository::class);
        $this->badgeService = Mockery::mock(BadgeService::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new CompetitionService(
            $this->competitionRepository,
            $this->badgeRepository,
            $this->badgeService,
            $this->databaseMock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}