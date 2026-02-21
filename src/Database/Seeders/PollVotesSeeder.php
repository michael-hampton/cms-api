<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Member;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Site;

class PollVotesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Site::all() as $site) {
            echo "Seeding poll votes for site: {$site->name} (ID: {$site->id})\n";
            $this->seedForSite($site->id);
        }
    }

    public function seedForSite(int $siteId): void
    {
        $polls = Poll::where('site_id', $siteId)
            ->where('status', 'active')
            ->with(['options'])
            ->get();

        if ($polls->isEmpty()) {
            echo "  No active polls found — run PollsSeeder first.\n";
            return;
        }

        $members = Member::where('site_id', $siteId)
            ->where('is_active', true)
            ->limit(20)
            ->get();

        if ($members->isEmpty()) {
            echo "  No members found — skipping vote seed.\n";
            return;
        }

        foreach ($polls as $poll) {
            $options = $poll->options;
            $optionIds = $options->pluck('id')->toArray();
            $totalOpts = count($optionIds);

            if (!$totalOpts) continue;

            // Weight first two options more heavily so results look realistic
            $weights = [];
            foreach ($optionIds as $i => $optId) {
                $weights[$optId] = max(1, $totalOpts - $i);
            }
            $weightTotal = array_sum($weights);

            $voteCount = 0;

            foreach ($members as $member) {
                // Already voted? skip
                $exists = PollVote::where('poll_id', $poll->id)
                    ->where('member_id', $member->id)
                    ->exists();

                if ($exists) continue;

                // Weighted random option selection
                $rand = rand(1, $weightTotal);
                $cumWeight = 0;
                $chosen = $optionIds[0];

                foreach ($weights as $optId => $weight) {
                    $cumWeight += $weight;
                    if ($rand <= $cumWeight) {
                        $chosen = $optId;
                        break;
                    }
                }

                PollVote::create([
                    'poll_id' => $poll->id,
                    'poll_option_id' => $chosen,
                    'member_id' => $member->id,
                    'voted_at' => now_datetime()->modify('-' . rand(0, 5) . ' days')->format('Y-m-d H:i:s'),
                ]);

                $voteCount++;
            }

            echo "  Seeded {$voteCount} votes for poll: {$poll->question}\n";
        }
    }
}