<?php

namespace App\Database\Seeders;

use App\Models\Badge;

class BadgeSeeder
{
    public function run()
    {
        $siteId = 52;

        $badges = [
            // Engagement Badges
            [
                'site_id' => $siteId,
                'name' => 'First Steps',
                'slug' => 'first-steps',
                'description' => 'Posted your first comment',
                'icon' => '👣',
                'tier' => 'bronze',
                'category' => 'engagement',
                'criteria' => [
                    ['type' => 'comments_count', 'operator' => '>=', 'value' => 1]
                ],
                'points' => 50
            ],
            [
                'site_id' => $siteId,
                'name' => 'Conversationalist',
                'slug' => 'conversationalist',
                'description' => 'Posted 10 comments',
                'icon' => '💬',
                'tier' => 'silver',
                'category' => 'engagement',
                'criteria' => [
                    ['type' => 'comments_count', 'operator' => '>=', 'value' => 10]
                ],
                'points' => 100
            ],
            [
                'site_id' => $siteId,
                'name' => 'Chatterbox',
                'slug' => 'chatterbox',
                'description' => 'Posted 50 comments',
                'icon' => '🗣️',
                'tier' => 'gold',
                'category' => 'engagement',
                'criteria' => [
                    ['type' => 'comments_count', 'operator' => '>=', 'value' => 50]
                ],
                'points' => 250
            ],

            // Content Badges
            [
                'site_id' => $siteId,
                'name' => 'Bookworm',
                'slug' => 'bookworm',
                'description' => 'Read 10 pages',
                'icon' => '📚',
                'tier' => 'bronze',
                'category' => 'content',
                'criteria' => [
                    ['type' => 'pages_read', 'operator' => '>=', 'value' => 10]
                ],
                'points' => 50
            ],
            [
                'site_id' => $siteId,
                'name' => 'Scholar',
                'slug' => 'scholar',
                'description' => 'Read 50 pages',
                'icon' => '🎓',
                'tier' => 'silver',
                'category' => 'content',
                'criteria' => [
                    ['type' => 'pages_read', 'operator' => '>=', 'value' => 50]
                ],
                'points' => 150
            ],
            [
                'site_id' => $siteId,
                'name' => 'Encyclopedia',
                'slug' => 'encyclopedia',
                'description' => 'Read 100 pages',
                'icon' => '📖',
                'tier' => 'gold',
                'category' => 'content',
                'criteria' => [
                    ['type' => 'pages_read', 'operator' => '>=', 'value' => 100]
                ],
                'points' => 300
            ],

            // Loyalty Badges
            [
                'site_id' => $siteId,
                'name' => 'Newbie',
                'slug' => 'newbie',
                'description' => 'Been a member for 7 days',
                'icon' => '🌱',
                'tier' => 'bronze',
                'category' => 'loyalty',
                'criteria' => [
                    ['type' => 'member_days', 'operator' => '>=', 'value' => 7]
                ],
                'points' => 25
            ],
            [
                'site_id' => $siteId,
                'name' => 'Regular',
                'slug' => 'regular',
                'description' => 'Been a member for 30 days',
                'icon' => '🌿',
                'tier' => 'silver',
                'category' => 'loyalty',
                'criteria' => [
                    ['type' => 'member_days', 'operator' => '>=', 'value' => 30]
                ],
                'points' => 100
            ],
            [
                'site_id' => $siteId,
                'name' => 'Veteran',
                'slug' => 'veteran',
                'description' => 'Been a member for 90 days',
                'icon' => '🌳',
                'tier' => 'gold',
                'category' => 'loyalty',
                'criteria' => [
                    ['type' => 'member_days', 'operator' => '>=', 'value' => 90]
                ],
                'points' => 250
            ],
            [
                'site_id' => $siteId,
                'name' => 'Legend',
                'slug' => 'legend',
                'description' => 'Been a member for 365 days',
                'icon' => '👑',
                'tier' => 'platinum',
                'category' => 'loyalty',
                'criteria' => [
                    ['type' => 'member_days', 'operator' => '>=', 'value' => 365]
                ],
                'points' => 1000
            ],

            // Engagement - Likes
            [
                'site_id' => $siteId,
                'name' => 'Appreciator',
                'slug' => 'appreciator',
                'description' => 'Liked 10 pages',
                'icon' => '❤️',
                'tier' => 'bronze',
                'category' => 'engagement',
                'criteria' => [
                    ['type' => 'likes_given', 'operator' => '>=', 'value' => 10]
                ],
                'points' => 50
            ],
            [
                'site_id' => $siteId,
                'name' => 'Super Fan',
                'slug' => 'super-fan',
                'description' => 'Liked 50 pages',
                'icon' => '⭐',
                'tier' => 'silver',
                'category' => 'engagement',
                'criteria' => [
                    ['type' => 'likes_given', 'operator' => '>=', 'value' => 50]
                ],
                'points' => 150
            ],

            // Special Purchase Badges
            [
                'site_id' => $siteId,
                'name' => 'First Purchase',
                'slug' => 'first-purchase',
                'description' => 'Made your first purchase',
                'icon' => '🛍️',
                'tier' => 'bronze',
                'category' => 'special',
                'criteria' => [
                    ['type' => 'orders_count', 'operator' => '>=', 'value' => 1]
                ],
                'points' => 100
            ],
            [
                'site_id' => $siteId,
                'name' => 'Loyal Customer',
                'slug' => 'loyal-customer',
                'description' => 'Made 5 purchases',
                'icon' => '🏆',
                'tier' => 'silver',
                'category' => 'special',
                'criteria' => [
                    ['type' => 'orders_count', 'operator' => '>=', 'value' => 5]
                ],
                'points' => 200
            ],
            [
                'site_id' => $siteId,
                'name' => 'VIP Shopper',
                'slug' => 'vip-shopper',
                'description' => 'Made 10 purchases',
                'icon' => '💎',
                'tier' => 'gold',
                'category' => 'special',
                'criteria' => [
                    ['type' => 'orders_count', 'operator' => '>=', 'value' => 10]
                ],
                'points' => 500
            ],
            [
                'site_id' => $siteId,
                'name' => 'Big Spender',
                'slug' => 'big-spender',
                'description' => 'Spent over £1000',
                'icon' => '💰',
                'tier' => 'platinum',
                'category' => 'special',
                'criteria' => [
                    ['type' => 'total_spent', 'operator' => '>=', 'value' => 1000]
                ],
                'points' => 1000
            ],
        ];

        //$sites = Site::all();

        //foreach ($sites as $site) {
            foreach ($badges as $badge) {
                $badge['site_id'] = $siteId;
                Badge::create($badge);
            }
        //}
    }
}