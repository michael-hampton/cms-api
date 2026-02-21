<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\LeaderboardEntry;
use App\Models\Member;
use App\Models\Site;

class LeaderboardSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Site::all() as $site) {
            echo "Seeding leaderboard for site: {$site->name} (ID: {$site->id})\n";
            $this->seedForSite($site->id);
        }
    }

    public function seedForSite(int $siteId): void
    {
        $weekStart = LeaderboardEntry::currentWeekStart();

        // Grab up to 10 real members for this site to seed realistic data
        $members = Member::where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->limit(10)
            ->get();

        if ($members->isEmpty()) {
            echo "  No verified members found for site {$siteId} — skipping leaderboard seed.\n";
            return;
        }

        // Shuffle so ranks feel natural rather than always the same member winning
        $memberIds = $members->pluck('id')->shuffle()->values();

        // Points leaderboard — descending scores
        $pointsScores = $this->descendingScores(count($memberIds), 980, 60);
        // Activity leaderboard — smaller numbers (count of activities)
        $activityScores = $this->descendingScores(count($memberIds), 47, 2);

        foreach ($memberIds as $i => $memberId) {
            // Points
            LeaderboardEntry::updateOrCreate(
                [
                    'site_id' => $siteId,
                    'member_id' => $memberId,
                    'type' => 'points',
                    'week_start' => $weekStart,
                ],
                [
                    'period' => 'weekly',
                    'score' => $pointsScores[$i],
                    'rank' => $i + 1,
                ]
            );

            // Activity
            LeaderboardEntry::updateOrCreate(
                [
                    'site_id' => $siteId,
                    'member_id' => $memberId,
                    'type' => 'activity',
                    'week_start' => $weekStart,
                ],
                [
                    'period' => 'weekly',
                    'score' => $activityScores[$i],
                    'rank' => $i + 1,
                ]
            );

            $name = $members->firstWhere('id', $memberId)?->first_name ?? "Member {$memberId}";
            echo "  Seeded leaderboard for {$name} — points: {$pointsScores[$i]}, activity: {$activityScores[$i]}\n";
        }
    }

    /**
     * Generate $count descending scores starting near $max,
     * decreasing by a random amount each step, never below $min.
     */
    private function descendingScores(int $count, int $max, int $min): array
    {
        $scores = [];
        $current = $max;

        for ($i = 0; $i < $count; $i++) {
            $scores[] = max($min, $current);
            // Random drop between 5-20% of current value
            $drop = (int)round($current * (rand(5, 20) / 100));
            $current = max($min, $current - max(1, $drop));
        }

        return $scores;
    }
}