<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Badge;
use App\Models\Competition;
use App\Models\Site;

/**
 * CompetitionSeeder
 *
 * Populates competitions, badges referenced by competition criteria,
 * and the site record they belong to.
 *
 * Run with:
 *   php artisan db:seed --class=CompetitionSeeder
 *
 * Or add to DatabaseSeeder::run():
 *   $this->call(CompetitionSeeder::class);
 */
class CompetitionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Site ────────────────────────────────────────────────
        // Resolve the primary site — create one if it doesn't exist yet.
        $site = Site::firstOrCreate(
            ['slug' => 'fourfour two'],
            ['name' => 'FourFourTwo']
        );

        // ── Badges used as entry criteria ───────────────────────
        $regularReader = Badge::firstOrCreate(
            ['site_id' => $site->id, 'name' => 'Regular Reader'],
            [
                'is_active' => true,
                'points' => 50,
                'sort_order' => 1,
                'category' => 'engagement',
                'criteria' => [
                    ['type' => 'pages_read', 'operator' => '>=', 'value' => 10],
                ],
                'slug' => 'regular-reader'
            ]
        );

        $commenter = Badge::firstOrCreate(
            ['site_id' => $site->id, 'name' => 'Commenter'],
            [
                'is_active' => true,
                'points' => 30,
                'sort_order' => 2,
                'category' => 'engagement',
                'criteria' => [
                    ['type' => 'comments_count', 'operator' => '>=', 'value' => 5],
                ],
                'slug' => 'commenter'
            ]
        );

        $loyalFan = Badge::firstOrCreate(
            ['site_id' => $site->id, 'name' => 'Loyal Fan'],
            [
                'is_active' => true,
                'points' => 100,
                'sort_order' => 3,
                'category' => 'loyalty',
                'criteria' => [
                    ['type' => 'member_days', 'operator' => '>=', 'value' => 30],
                ],
                'slug' => 'loyal-fan'
            ]
        );

        // ── Competitions ─────────────────────────────────────────
        $competitions = [

            // 1 — Open draw (featured)
            [
                'site_id' => $site->id,
                'title' => 'Win a PS5™ Controller Revolution 5 Pro Forest Camo',
                'description' => 'Exclusive prize draw for FourFourTwo Club members. The ultimate pro controller with 3 back buttons, Hall Effect thumbsticks, and fully remappable controls.',
                'slug' => 'ps5-controller-forest-camo',
                'status' => 'active',
                'entry_type' => 'open',
                'starts_at' => now(),
                'ends_at' => now_datetime()->addDays(30),
                'prize_description' => 'worth £179.99',
                'is_featured' => true,
                'sort_order' => 1,
                'settings' => [
                    'sponsor' => 'nacon',
                    'entry_criteria' => [],
                ],
            ],

            // 2 — Activity-gated (coming soon)
            [
                'site_id' => $site->id,
                'title' => 'Revolution X Unlimited Controller',
                'description' => 'Next prize draw for FourFourTwo Club members. Return to the site 7 times in 30 days, completing 3 actions each visit, to unlock entry.',
                'slug' => 'revolution-x-unlimited',
                'status' => 'active',
                'entry_type' => 'activity',
                'starts_at' => now_datetime()->addDays(14),
                'ends_at' => now_datetime()->addDays(60),
                'prize_description' => 'worth £279.99',
                'is_featured' => false,
                'sort_order' => 2,
                'settings' => [
                    'sponsor' => 'nacon',
                    'entry_criteria' => [
                        [
                            'type' => 'return_visits',
                            'visits' => 7,
                            'actions_per_visit' => 3,
                            'action_types' => ['comment', 'article_read', 'game_play'],
                            'within_days' => 30,
                        ],
                    ],
                ],
            ],

            // 3 — Badge-gated (specific badge IDs)
            [
                'site_id' => $site->id,
                'title' => 'Brazil 1970 World Cup Shirt Signed by Pelé',
                'description' => 'A rare piece of football history. Earn 3 engagement badges to unlock entry to this exclusive competition.',
                'slug' => 'brazil-1970-pele-shirt',
                'status' => 'active',
                'entry_type' => 'badge',
                'starts_at' => now(),
                'ends_at' => now_datetime()->addDays(45),
                'prize_description' => null,
                'is_featured' => false,
                'sort_order' => 3,
                'settings' => [
                    'sponsor' => 'Polo Ralph Lauren',
                    'entry_criteria' => [
                        [
                            'type' => 'badge_ids',
                            'badge_ids' => [$regularReader->id, $commenter->id, $loyalFan->id],
                        ],
                    ],
                ],
            ],

            // 4 — Badge count
            [
                'site_id' => $site->id,
                'title' => 'RIG RS Spear PRO H5 Headset',
                'description' => 'Earn any 2 badges on FourFourTwo to unlock entry to this prize draw.',
                'slug' => 'rig-rs-spear-pro-h5',
                'status' => 'active',
                'entry_type' => 'badge',
                'starts_at' => now(),
                'ends_at' => now_datetime()->addDays(21),
                'prize_description' => 'worth £179.99',
                'is_featured' => false,
                'sort_order' => 4,
                'settings' => [
                    'sponsor' => 'nacon | RIG',
                    'entry_criteria' => [
                        [
                            'type' => 'badge_count',
                            'value' => 2,
                        ],
                    ],
                ],
            ],

            // 5 — Referral
            [
                'site_id' => $site->id,
                'title' => 'Barcelona Home Shirt & Gillette Bundle',
                'description' => 'Refer a friend who creates a FourFourTwo Club account to unlock your chance of winning this exclusive prize bundle.',
                'slug' => 'barcelona-shirt-gillette',
                'status' => 'active',
                'entry_type' => 'referral',
                'starts_at' => now(),
                'ends_at' => now_datetime()->addDays(30),
                'prize_description' => null,
                'is_featured' => false,
                'sort_order' => 5,
                'settings' => [
                    'sponsor' => 'Rakuten',
                    'entry_criteria' => [
                        [
                            'type' => 'referral',
                            'value' => 1,
                            'referred_count' => 0,
                        ],
                    ],
                ],
            ],

            // 6 — Raffle
            [
                'site_id' => $site->id,
                'title' => '3× Tickets – Chelsea vs Liverpool',
                'description' => 'Enter our raffle for three tickets to one of the season\'s biggest Premier League matches. Winner drawn at random from all entries.',
                'slug' => 'chelsea-vs-liverpool-tickets',
                'status' => 'active',
                'entry_type' => 'raffle',
                'starts_at' => now(),
                'ends_at' => now_datetime()->addDays(7),
                'prize_description' => null,
                'is_featured' => false,
                'sort_order' => 6,
                'settings' => [
                    'sponsor' => null,
                    'entry_criteria' => [],
                ],
            ],

            // 7 — Sponsored external
            [
                'site_id' => $site->id,
                'title' => 'Win a 55″ Samsung QLED TV',
                'description' => 'Visit the Samsung online store to qualify for entry. Samsung is gifting this prize exclusively to one FourFourTwo Club member.',
                'slug' => 'samsung-qled-tv',
                'status' => 'active',
                'entry_type' => 'sponsored',
                'starts_at' => now(),
                'ends_at' => now_datetime()->addDays(14),
                'prize_description' => 'worth £1,299',
                'is_featured' => false,
                'sort_order' => 7,
                'settings' => [
                    'sponsor' => 'Samsung',
                    'external_url' => 'https://samsung.com/uk/promo/fourfour',
                    'entry_criteria' => [],
                ],
            ],

            // 8 — Ended
            [
                'site_id' => $site->id,
                'title' => 'Full-Time Football Scholarships',
                'description' => 'A previous FourFourTwo Club member competition. Congratulations to our winner.',
                'slug' => 'full-time-scholarships',
                'status' => 'ended',
                'entry_type' => 'open',
                'starts_at' => now_datetime()->subDays(60),
                'ends_at' => now_datetime()->subDays(1),
                'prize_description' => null,
                'is_featured' => false,
                'sort_order' => 8,
                'settings' => [
                    'sponsor' => null,
                    'entry_criteria' => [],
                ],
            ],
        ];

        foreach ($competitions as $data) {
            Competition::updateOrCreate(
                ['site_id' => $data['site_id'], 'slug' => $data['slug']],
                $data
            );
        }

        echo 'CompetitionSeeder: ' . count($competitions) . ' competitions seeded for site "' . $site->name . '".';
    }
}