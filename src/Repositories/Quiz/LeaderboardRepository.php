<?php

namespace App\Repositories\Quiz;

use App\Framework\Support\Collection;
use App\Models\LeaderboardEntry;
use App\Models\MemberActivity;
use App\Models\MemberPoint;
use App\Repositories\Repository;

class LeaderboardRepository extends Repository
{
    public function getTopForWeek(int $siteId, string $type, int $limit = 10): Collection
    {
        $weekStart = LeaderboardEntry::currentWeekStart();

        return LeaderboardEntry::where('site_id', $siteId)
            ->where('type', $type)
            ->where('week_start', $weekStart)
            ->with(['member'])
            ->orderBy('score', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getMemberRank(int $memberId, int $siteId, string $type): ?LeaderboardEntry
    {
        $weekStart = LeaderboardEntry::currentWeekStart();

        return LeaderboardEntry::where('site_id', $siteId)
            ->where('member_id', $memberId)
            ->where('type', $type)
            ->where('week_start', $weekStart)
            ->first();
    }

    /**
     * Rebuild leaderboard scores from source data for the current week.
     * Called by a scheduled job, not on every request.
     */
    public function rebuildForWeek(int $siteId): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +7 days'));

        // Points leaderboard — sum MemberPoints awarded this week, scoped to site
        // via the member relationship: MemberPoint → members.site_id
        $pointsData = MemberPoint::selectRaw('member_points.member_id, SUM(member_points.points) as total')
            ->join('members', 'members.id', '=', 'member_points.member_id')
            ->where('members.site_id', $siteId)
            ->whereBetween('member_points.awarded_at', [$weekStart, $weekEnd])
            ->groupBy('member_points.member_id')
            ->get();

        foreach ($pointsData as $row) {
            LeaderboardEntry::updateOrCreate(
                [
                    'site_id' => $siteId,
                    'member_id' => $row->member_id,
                    'type' => 'points',
                    'week_start' => $weekStart,
                ],
                ['score' => $row->total, 'period' => 'weekly']
            );
        }

        // Activity leaderboard — count MemberActivity rows this week
        $activityData = MemberActivity::selectRaw('member_id, COUNT(*) as total')
            ->where('site_id', $siteId)
            ->whereBetween('activity_date', [$weekStart, $weekEnd])
            ->groupBy('member_id')
            ->get();

        foreach ($activityData as $row) {
            LeaderboardEntry::updateOrCreate(
                [
                    'site_id' => $siteId,
                    'member_id' => $row->member_id,
                    'type' => 'activity',
                    'week_start' => $weekStart,
                ],
                ['score' => $row->total, 'period' => 'weekly']
            );
        }

        // Assign ranks after scores are set
        // NOTE: This issues N individual UPDATE calls — one per entry. For large
        // leaderboards consider replacing with a single UPDATE ... JOIN or raw CASE WHEN.
        foreach (['points', 'activity'] as $type) {
            $entries = LeaderboardEntry::where('site_id', $siteId)
                ->where('type', $type)
                ->where('week_start', $weekStart)
                ->orderBy('score', 'desc')
                ->get();

            foreach ($entries as $i => $entry) {
                $entry->update(['rank' => $i + 1]);
            }
        }
    }

    protected function getModelClass(): string
    {
        return LeaderboardEntry::class;
    }
}