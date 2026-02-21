<?php

namespace App\Tests\Unit\Repositories\Quiz;

use App\Models\LeaderboardEntry;
use App\Models\MemberActivity;
use App\Models\MemberPoint;
use App\Models\Site;
use App\Repositories\Quiz\LeaderboardRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class LeaderboardRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private LeaderboardRepository $repository;

    public function test_get_top_for_week_returns_entries_for_correct_site_and_type(): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();
        $member = $this->createMember();

        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'points', 'score' => 100, 'week_start' => $weekStart]);
        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'activity', 'score' => 50, 'week_start' => $weekStart]);

        $results = $this->repository->getTopForWeek($this->siteId, 'points');

        $this->assertCount(1, $results);
        $this->assertSame('points', $results->first()->type);
    }

    // -------------------------------------------------------------------------
    // getTopForWeek
    // -------------------------------------------------------------------------

    private function createEntry(array $attributes): LeaderboardEntry
    {
        return LeaderboardEntry::create(array_merge([
            'period' => 'weekly',
            'rank' => 1,
        ], $attributes));
    }

    public function test_get_top_for_week_excludes_other_sites(): void
    {
        $otherSite = Site::create(['name' => 'test', 'slug' => 'test']);
        $weekStart = LeaderboardEntry::currentWeekStart();

        $member = $this->createMember();

        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'points', 'score' => 100, 'week_start' => $weekStart]);
        $this->createEntry(['site_id' => $otherSite->id, 'member_id' => $member->id, 'type' => 'points', 'score' => 999, 'week_start' => $weekStart]);

        $results = $this->repository->getTopForWeek($this->siteId, 'points');

        $this->assertCount(1, $results);
        $this->assertSame($this->siteId, $results->first()->site_id);
    }

    public function test_get_top_for_week_excludes_previous_weeks(): void
    {
        $thisWeek = LeaderboardEntry::currentWeekStart();
        $lastWeek = date('Y-m-d', strtotime($thisWeek . ' -7 days'));
        $member = $this->createMember();

        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'points', 'score' => 100, 'week_start' => $thisWeek]);
        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'points', 'score' => 200, 'week_start' => $lastWeek]);

        $results = $this->repository->getTopForWeek($this->siteId, 'points');

        $this->assertCount(1, $results);
        $this->assertSame(100, (int)$results->first()->score);
    }

    public function test_get_top_for_week_returns_entries_ordered_by_score_descending(): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();
        $m1 = $this->createMember();
        $m2 = $this->createMember();
        $m3 = $this->createMember();

        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $m1->id, 'type' => 'points', 'score' => 30, 'week_start' => $weekStart]);
        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $m2->id, 'type' => 'points', 'score' => 90, 'week_start' => $weekStart]);
        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $m3->id, 'type' => 'points', 'score' => 60, 'week_start' => $weekStart]);

        $results = $this->repository->getTopForWeek($this->siteId, 'points');

        $scores = $results->pluck('score')->map(fn($s) => (int)$s)->toArray();
        $this->assertSame([90, 60, 30], $scores);
    }

    public function test_get_top_for_week_respects_limit(): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();

        for ($i = 1; $i <= 15; $i++) {
            $member = $this->createMember();
            $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'points', 'score' => $i, 'week_start' => $weekStart]);
        }

        $results = $this->repository->getTopForWeek($this->siteId, 'points', limit: 10);

        $this->assertCount(10, $results);
    }

    public function test_get_top_for_week_eager_loads_member(): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();
        $member = $this->createMember();
        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'points', 'score' => 50, 'week_start' => $weekStart]);

        $results = $this->repository->getTopForWeek($this->siteId, 'points');

        $this->assertTrue($results->first()->relationLoaded('member'));
    }

    // -------------------------------------------------------------------------
    // getMemberRank
    // -------------------------------------------------------------------------

    public function test_get_member_rank_returns_entry_for_current_week(): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();
        $member = $this->createMember();
        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'points', 'score' => 75, 'week_start' => $weekStart]);

        $entry = $this->repository->getMemberRank($member->id, $this->siteId, 'points');

        $this->assertNotNull($entry);
        $this->assertSame($member->id, $entry->member_id);
    }

    public function test_get_member_rank_returns_null_when_no_entry(): void
    {
        $member = $this->createMember();

        $entry = $this->repository->getMemberRank($member->id, $this->siteId, 'points');

        $this->assertNull($entry);
    }

    public function test_get_member_rank_is_scoped_to_type(): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();
        $member = $this->createMember();
        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'activity', 'score' => 10, 'week_start' => $weekStart]);

        $entry = $this->repository->getMemberRank($member->id, $this->siteId, 'points');

        $this->assertNull($entry);
    }

    public function test_get_member_rank_excludes_previous_weeks(): void
    {
        $thisWeek = LeaderboardEntry::currentWeekStart();
        $lastWeek = date('Y-m-d', strtotime($thisWeek . ' -7 days'));
        $member = $this->createMember();

        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'points', 'score' => 100, 'week_start' => $lastWeek]);

        $entry = $this->repository->getMemberRank($member->id, $this->siteId, 'points');

        $this->assertNull($entry);
    }

    // -------------------------------------------------------------------------
    // rebuildForWeek
    // -------------------------------------------------------------------------

    public function test_rebuild_for_week_aggregates_member_points_via_activity(): void
    {
        $member = $this->createMember();
        $weekStart = LeaderboardEntry::currentWeekStart();
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +7 days'));

        // Points linked to an activity on this site
        $this->createMemberPointForSite($member->id, $this->siteId, 50, $weekStart);
        $this->createMemberPointForSite($member->id, $this->siteId, 30, $weekStart);

        $this->repository->rebuildForWeek($this->siteId);

        $entry = LeaderboardEntry::where('member_id', $member->id)
            ->where('site_id', $this->siteId)
            ->where('type', 'points')
            ->where('week_start', $weekStart)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(80, (int)$entry->score);
    }

    /**
     * Creates a MemberPoint linked to a MemberActivity on the given site.
     * This is the join path: MemberPoint → Activity → site_id.
     */
    private function createMemberPointForSite(int $memberId, int $siteId, int $points, string $awardedAt): void
    {
        $activity = MemberActivity::create([
            'member_id' => $memberId,
            'site_id' => $siteId,
            'activity_date' => $awardedAt,
            'activity_type' => 'test',
        ]);

        MemberPoint::create([
            'member_id' => $memberId,
            'activity_id' => $activity->id,
            'points' => $points,
            'awarded_at' => $awardedAt,
            'reason' => 'test'
        ]);
    }

    public function test_rebuild_for_week_does_not_include_other_sites_points(): void
    {
        $otherSite = Site::create(['name' => 'test', 'slug' => 'test']);
        $memberThisSite = $this->createMember(['site_id' => $this->siteId]);
        $memberOtherSite = $this->createMember(['site_id' => $otherSite->id]);
        $weekStart = LeaderboardEntry::currentWeekStart();

        $this->createMemberPoint($memberThisSite->id, 40, $weekStart);
        $this->createMemberPoint($memberOtherSite->id, 999, $weekStart);

        $this->repository->rebuildForWeek($this->siteId);

        // This site's member gets the correct score
        $entry = LeaderboardEntry::where('member_id', $memberThisSite->id)
            ->where('site_id', $this->siteId)
            ->where('type', 'points')
            ->where('week_start', $weekStart)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(40, (int)$entry->score);

        // The other-site member must not appear on this site's leaderboard at all
        $spillover = LeaderboardEntry::where('member_id', $memberOtherSite->id)
            ->where('site_id', $this->siteId)
            ->where('type', 'points')
            ->first();

        $this->assertNull($spillover);
    }

    /**
     * Creates a MemberPoint for the given member.
     * No site_id on MemberPoint — site scoping in rebuildForWeek is achieved by
     * joining MemberPoint → members.site_id. The site is therefore determined by
     * whichever site the member belongs to.
     */
    private function createMemberPoint(int $memberId, int $points, string $awardedAt): void
    {
        MemberPoint::create([
            'member_id' => $memberId,
            'points' => $points,
            'awarded_at' => $awardedAt,
            'reason' => 'test'
        ]);
    }

    public function test_rebuild_for_week_excludes_points_outside_current_week(): void
    {
        $member = $this->createMember();
        $weekStart = LeaderboardEntry::currentWeekStart();
        $lastWeek = date('Y-m-d', strtotime($weekStart . ' -7 days'));

        $this->createMemberPointForSite($member->id, $this->siteId, 100, $lastWeek);

        $this->repository->rebuildForWeek($this->siteId);

        $entry = LeaderboardEntry::where('member_id', $member->id)
            ->where('site_id', $this->siteId)
            ->where('type', 'points')
            ->first();

        $this->assertNull($entry);
    }

    public function test_rebuild_for_week_aggregates_activity_counts(): void
    {
        $member = $this->createMember();
        $weekStart = LeaderboardEntry::currentWeekStart();

        $this->createMemberActivity($member->id, $this->siteId, $weekStart);
        $this->createMemberActivity($member->id, $this->siteId, $weekStart);
        $this->createMemberActivity($member->id, $this->siteId, $weekStart);

        $this->repository->rebuildForWeek($this->siteId);

        $entry = LeaderboardEntry::where('member_id', $member->id)
            ->where('site_id', $this->siteId)
            ->where('type', 'activity')
            ->where('week_start', $weekStart)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(3, (int)$entry->score);
    }

    private function createMemberActivity(int $memberId, int $siteId, string $date, int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            MemberActivity::create([
                'member_id' => $memberId,
                'site_id' => $siteId,
                'activity_date' => $date,
                'activity_type' => 'test',
                'points' => 5
            ]);
        }
    }

    public function test_rebuild_for_week_excludes_activity_from_other_sites(): void
    {
        $member = $this->createMember();
        $otherSite = Site::create(['name' => 'test', 'slug' => 'test']);
        $weekStart = LeaderboardEntry::currentWeekStart();

        $this->createMemberActivity($member->id, $this->siteId, $weekStart);
        $this->createMemberActivity($member->id, $otherSite->id, $weekStart);

        $this->repository->rebuildForWeek($this->siteId);

        $entry = LeaderboardEntry::where('member_id', $member->id)
            ->where('site_id', $this->siteId)
            ->where('type', 'activity')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(1, (int)$entry->score);
    }

    public function test_rebuild_for_week_assigns_sequential_ranks(): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();
        $m1 = $this->createMember();
        $m2 = $this->createMember();
        $m3 = $this->createMember();

        $this->createMemberActivity($m1->id, $this->siteId, $weekStart, count: 10);
        $this->createMemberActivity($m2->id, $this->siteId, $weekStart, count: 5);
        $this->createMemberActivity($m3->id, $this->siteId, $weekStart, count: 1);

        $this->repository->rebuildForWeek($this->siteId);

        $entries = LeaderboardEntry::where('site_id', $this->siteId)
            ->where('type', 'activity')
            ->where('week_start', $weekStart)
            ->orderBy('rank')
            ->get();

        $this->assertSame([1, 2, 3], $entries->pluck('rank')->map(fn($r) => (int)$r)->toArray());
        $this->assertSame($m1->id, $entries->first()->member_id);
    }

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    public function test_rebuild_for_week_upserts_existing_entries(): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();
        $member = $this->createMember();

        // Pre-existing stale entry
        $this->createEntry(['site_id' => $this->siteId, 'member_id' => $member->id, 'type' => 'activity', 'score' => 999, 'week_start' => $weekStart]);
        $this->createMemberActivity($member->id, $this->siteId, $weekStart, count: 2);

        $this->repository->rebuildForWeek($this->siteId);

        $entries = LeaderboardEntry::where('member_id', $member->id)
            ->where('site_id', $this->siteId)
            ->where('type', 'activity')
            ->where('week_start', $weekStart)
            ->get();

        $this->assertCount(1, $entries);
        $this->assertSame(2, (int)$entries->first()->score);
    }

    public function test_rebuild_for_week_assigns_ranks_for_both_types(): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();
        $m1 = $this->createMember();
        $m2 = $this->createMember();

        $this->createMemberPointForSite($m1->id, $this->siteId, 100, $weekStart);
        $this->createMemberPointForSite($m2->id, $this->siteId, 50, $weekStart);
        $this->createMemberActivity($m1->id, $this->siteId, $weekStart);
        $this->createMemberActivity($m2->id, $this->siteId, $weekStart, count: 3);

        $this->repository->rebuildForWeek($this->siteId);

        $pointsRankCount = LeaderboardEntry::where('site_id', $this->siteId)
            ->where('type', 'points')
            ->where('week_start', $weekStart)
            ->get()
            ->whereNotNull('rank')
            ->count();

        $activityRankCount = LeaderboardEntry::where('site_id', $this->siteId)
            ->where('type', 'activity')
            ->where('week_start', $weekStart)
            ->get()
            ->whereNotNull('rank')
            ->count();

        $this->assertSame(2, $pointsRankCount);
        $this->assertSame(2, $activityRankCount);
    }

    /**
     * Performance smell: rebuildForWeek issues N individual UPDATE calls (one per entry)
     * to assign ranks. For large leaderboards this becomes expensive.
     * Consider replacing with a single UPDATE ... JOIN or a raw CASE WHEN query.
     */
    public function test_rebuild_for_week_rank_assignment_is_correct(): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();
        $members = array_map(fn($_) => $this->createMember(), range(1, 5));
        $scores = [50, 20, 80, 10, 60];

        foreach ($members as $i => $member) {
            $this->createMemberActivity($member->id, $this->siteId, $weekStart, count: $scores[$i]);
        }

        $this->repository->rebuildForWeek($this->siteId);

        $entries = LeaderboardEntry::where('site_id', $this->siteId)
            ->where('type', 'activity')
            ->where('week_start', $weekStart)
            ->orderBy('rank')
            ->get();

        // Scores descending: 80, 60, 50, 20, 10 → ranks 1–5
        $this->assertSame(
            [80, 60, 50, 20, 10],
            $entries->pluck('score')->map(fn($s) => (int)$s)->toArray()
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new LeaderboardRepository();
    }
}