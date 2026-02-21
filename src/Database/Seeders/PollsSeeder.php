<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Site;

class PollsSeeder extends Seeder
{
    public function run(): void
    {
        $sites = Site::all();

        foreach ($sites as $site) {
            echo "Seeding polls for site: {$site->name} (ID: {$site->id})\n";
            $this->seedForSite($site->id);
        }
    }

    private function seedForSite(int $siteId): void
    {
        $polls = [
            [
                'question' => 'Who will win the league title this season?',
                'options' => ['Manchester City', 'Arsenal', 'Liverpool', 'Chelsea'],
            ],
            [
                'question' => 'What is the most exciting format in football?',
                'options' => [
                    'Champions League knockouts',
                    'World Cup group stage',
                    'Domestic league',
                    'FA Cup / domestic cups',
                ],
            ],
            [
                'question' => 'Which transfer window was the most exciting this year?',
                'options' => ['Summer window', 'January window'],
            ],
        ];

        foreach ($polls as $pollData) {
            $exists = Poll::where('site_id', $siteId)
                ->where('question', $pollData['question'])
                ->exists();

            if ($exists) {
                echo "  Skipping (exists): {$pollData['question']}\n";
                continue;
            }

            $poll = Poll::create([
                'site_id' => $siteId,
                'question' => $pollData['question'],
                'status' => 'active',
                'closes_at' => null,
            ]);

            foreach ($pollData['options'] as $i => $label) {
                PollOption::create([
                    'poll_id' => $poll->id,
                    'label' => $label,
                    'sort_order' => $i,
                ]);
            }

            echo "  Created: {$pollData['question']}\n";
        }
    }
}