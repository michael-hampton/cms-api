<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;

/**
 * Adds a team block to the homepage of every site that does not already have one.
 *
 * Safe to run multiple times — idempotent per site.
 */
class HomepageTeamBlockSeeder extends Seeder
{
    public function run(): void
    {
        $sites = Site::active()->get();

        foreach ($sites as $site) {
            $this->seedTeamBlockForSite($site);
        }
    }

    private function seedTeamBlockForSite(Site $site): void
    {
        $homepage = Page::where('slug', 'home')
            ->where('site_id', $site->id)
            ->first();

        if (!$homepage) {
            echo "  [SKIP] Site '{$site->slug}' has no homepage (slug=home).\n";
            return;
        }

        $alreadyHasTeamBlock = Block::where('page_id', $homepage->id)
            ->where('type', 'team')
            ->first();

        if ($alreadyHasTeamBlock) {
            echo "  [SKIP] Site '{$site->slug}' homepage already has a team block.\n";
            return;
        }

        Block::create([
            'page_id' => $homepage->id,
            'type' => 'team',
            'order' => 5,
            'data' => json_encode($this->buildTeamBlockData($site)),
        ]);

        echo "  [OK]   Added team block to '{$site->slug}' homepage (order=5).\n";
    }

    private function buildTeamBlockData(Site $site): array
    {
        return [
            'title' => 'Meet Our Team',
            'subtitle' => 'The people behind ' . ($site->name ?? $site->slug),
            'layout' => 'grid',
            'members' => [
                [
                    'name' => 'Jane Smith',
                    'role' => 'Editor in Chief',
                    'bio' => 'Jane leads our editorial team with over 15 years of experience in digital publishing.',
                    'email' => '',
                    'phone' => '',
                    'image' => [
                        'src' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&q=80',
                        'alt' => 'Jane Smith',
                    ],
                ],
                [
                    'name' => 'Mark Jones',
                    'role' => 'Senior Writer',
                    'bio' => 'Mark covers breaking news and in-depth features across a wide range of topics.',
                    'email' => '',
                    'phone' => '',
                    'image' => [
                        'src' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&q=80',
                        'alt' => 'Mark Jones',
                    ],
                ],
                [
                    'name' => 'Sarah Lee',
                    'role' => 'Content Strategist',
                    'bio' => 'Sarah shapes our content roadmap and ensures every piece resonates with our audience.',
                    'email' => '',
                    'phone' => '',
                    'image' => [
                        'src' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400&q=80',
                        'alt' => 'Sarah Lee',
                    ],
                ],
            ],
        ];
    }
}