<?php

namespace App\Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;

class SeedWineFooterMenu
{
    public function run(): void
    {
        $siteId = 3;

        $menu = Menu::create([
            'name' => 'Wine Footer Menu',
            'site_id' => $siteId,
            'menu_type' => 'footer',
            'slug' => 'wine-footer-menu',
            'layout_config' => json_encode([
                'footer_style' => 'wine',
                'max_columns' => 4,
                'show_brand_section' => true,
                'logo_type' => 'text',
                'logo_main' => 'The Wine Chronicle',
                'logo_sub' => '',
                'footer_description' => 'Expert wine criticism and education since 1975. Independent reviews from Master Sommeliers and Masters of Wine.',
                'social_style' => 'simple',
                'social_links' => [
                    'facebook' => '#',
                    'twitter' => '#',
                    'instagram' => '#',
                    'youtube' => '#'
                ],
                'show_newsletter' => false,
                'brand_name' => 'The Wine Chronicle',
                'copyright_text' => '© {year} {brand}. All rights reserved.',
                'footer_bottom_text' => 'Drink responsibly. Must be 18+ years old.',
                'legal_links' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy'],
                    ['label' => 'Terms of Service', 'url' => '/terms'],
                    ['label' => 'Cookie Policy', 'url' => '/cookies'],
                    ['label' => 'Advertise', 'url' => '/advertise']
                ]
            ]),
            'is_active' => true,
        ]);

        $menuId = $menu->id;

        // Column 1: Fashion
        $this->createFooterColumn($menuId, 1, 'Wine Reviews', [
            ['label' => 'Bordeaux', 'url' => '/bordeaux'],
            ['label' => 'Burgundy', 'url' => '/burgundy'],
            ['label' => 'Champagne', 'url' => '/champagne'],
            ['label' => 'Tuscany', 'url' => '/tuscany'],
            ['label' => 'Napa Valley', 'url' => '/napa'],
            ['label' => 'All Regions', 'url' => '/regions']
        ]);

        // Column 2: Beauty
        $this->createFooterColumn($menuId, 2, 'Wine Knowledge', [
            ['label' => 'Beginner\'s Guide', 'url' => '/beginners'],
            ['label' => 'Tasting Techniques', 'url' => '/tasting'],
            ['label' => 'Food Pairing', 'url' => '/pairing'],
            ['label' => 'Wine Storage', 'url' => '/storage'],
            ['label' => 'Grape Varieties', 'url' => '/grapes'],
            ['label' => 'Masterclasses', 'url' => '/masterclasses']
        ]);

        // Column 3: About
        $this->createFooterColumn($menuId, 3, 'About Us', [
            ['label' => 'Our Story', 'url' => '/about'],
            ['label' => 'Expert Team', 'url' => '/team'],
            ['label' => 'Rating System', 'url' => '/rating-system'],
            ['label' => 'Editorial Policy', 'url' => '/editorial'],
            ['label' => 'Events & Tastings', 'url' => '/events'],
            ['label' => 'Contact Us', 'url' => '/contact']
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