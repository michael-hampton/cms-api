<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Menu;
use App\Models\MenuItem;

class SeedMusicFooterMenu extends Seeder
{
    public function run(): void
    {
        $siteId = 7;

        $menu = Menu::create([
            'name' => 'Music Footer Menu',
            'site_id' => $siteId,
            'menu_type' => 'footer',
            'slug' => 'music-footer-menu',
            'layout_config' => json_encode([
                'footer_style' => 'modern',
                'max_columns' => 4,
                'show_brand_section' => true,
                'logo_type' => 'icon',
                'logo_icon' => '🎵',
                'brand_name' => 'Soundwave',
                'footer_description' => 'Your ultimate source for music news, artist interviews, album reviews, and the latest tracks from around the globe. Experience music like never before.',
                'social_style' => 'simple',
                'social_links' => [
                    'facebook' => '#',
                    'twitter' => '#',
                    'instagram' => '#',
                    'youtube' => '#',
                    'spotify' => '#',
                    'tiktok' => '#'
                ],
                'show_newsletter' => true,
                'newsletter_title' => 'Never Miss a Beat',
                'newsletter_description' => 'Get the hottest music news, exclusive interviews, and new releases delivered straight to your inbox every week.',
                'newsletter_placeholder' => 'Your email address',
                'newsletter_button_text' => 'Subscribe Free',
                'newsletter_action' => '/subscribe',
                'copyright_text' => '© {year} {brand}. All rights reserved.',
                'legal_links' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy'],
                    ['label' => 'Terms of Service', 'url' => '/terms'],
                    ['label' => 'Cookie Policy', 'url' => '/cookies'],
                    ['label' => 'Advertising', 'url' => '/advertise']
                ]
            ]),
            'is_active' => true,
        ]);

        $menuId = $menu->id;

        // Column 1: Fashion
        $this->createFooterColumn($menuId, 1, 'Music', [
            ['label' => 'Latest News', 'url' => '/news'],
            ['label' => 'Album Reviews', 'url' => '/reviews/albums'],
            ['label' => 'Track Reviews', 'url' => '/reviews/tracks'],
            ['label' => 'New Releases', 'url' => '/releases'],
            ['label' => 'Charts', 'url' => '/charts'],
            ['label' => 'Playlists', 'url' => '/playlists']
        ]);

        // Column 2: Beauty
        $this->createFooterColumn($menuId, 2, 'Genres', [
            ['label' => 'Rock', 'url' => '/genre/rock'],
            ['label' => 'Pop', 'url' => '/genre/pop'],
            ['label' => 'Hip-Hop', 'url' => '/genre/hip-hop'],
            ['label' => 'Electronic', 'url' => '/genre/electronic'],
            ['label' => 'Jazz', 'url' => '/genre/jazz'],
            ['label' => 'Classical', 'url' => '/genre/classical']
        ]);

        // Column 3: About
        $this->createFooterColumn($menuId, 3, 'Features', [
            ['label' => 'Artist Interviews', 'url' => '/interviews'],
            ['label' => 'Live Reviews', 'url' => '/live-reviews'],
            ['label' => 'Festival Coverage', 'url' => '/festivals'],
            ['label' => 'Music Videos', 'url' => '/videos'],
            ['label' => 'Podcasts', 'url' => '/podcasts'],
            ['label' => 'Opinion', 'url' => '/opinion']
        ]);

        $this->createFooterColumn($menuId, 4, 'About', [
            ['label' => 'About Soundwave', 'url' => '/about'],
            ['label' => 'Our Team', 'url' => '/team'],
            ['label' => 'Contact Us', 'url' => '/contact'],
            ['label' => 'Submit Music', 'url' => '/submit'],
            ['label' => 'Careers', 'url' => '/careers'],
            ['label' => 'Press Kit', 'url' => '/press']
        ]);
    }

    private function createFooterColumn(int $menuId, int $columnGroup, string $header, array $links): void
    {
        MenuItem::create([
            'menu_id' => $menuId,
            'label' => $header,
            'column_group' => $columnGroup,
            'sort_order' => 0,
            'is_active' => 1,
            'target_type' => 'custom',
            'custom_url' => '#'
        ]);

        // Link items
        $order = 1;
        foreach ($links as $link) {
            MenuItem::create([
                'menu_id' => $menuId,
                'label' => $link['label'],
                'column_group' => $columnGroup,
                'sort_order' => $order++,
                'is_active' => 1,
                'target_type' => 'custom',
                'custom_url' => $link['url']
            ]);
        }
    }
}