<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Menu;
use App\Models\MenuItem;

class SeedHomeGardenFooterMenu extends Seeder
{

    public function run(): void
    {
        $siteId = 8;

        $menu = Menu::create([
            'name' => 'Home Garden Footer Menu',
            'site_id' => $siteId,
            'menu_type' => 'footer',
            'slug' => 'home-garden-footer-menu',
            'layout_config' => json_encode([
                'footer_style' => 'modern',
                'max_columns' => 4,
                'show_brand_section' => true,
                'logo_type' => 'icon',
                'logo_icon' => '🏡',
                'brand_name' => 'Haven & Hearth',
                'footer_description' => 'Your trusted source for home design inspiration, gardening expertise, and product reviews. We help you create beautiful, comfortable living spaces that reflect your personal style.',
                'social_style' => 'simple',
                'social_links' => [
                    'facebook' => '#',
                    'instagram' => '#',
                    'pinterest' => '#',
                    'youtube' => '#',
                    'twitter' => '#'
                ],
                'show_newsletter' => true,
                'newsletter_title' => 'Newsletter',
                'newsletter_description' => 'Get weekly design inspiration, gardening tips, and exclusive deals delivered to your inbox.',
                'newsletter_placeholder' => 'Your email address',
                'newsletter_button_text' => 'Subscribe Free',
                'newsletter_action' => '/subscribe',
                'copyright_text' => '© {year} {brand}. All rights reserved.',
                'legal_links' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy'],
                    ['label' => 'Terms of Service', 'url' => '/terms'],
                    ['label' => 'Cookie Policy', 'url' => '/cookies'],
                    ['label' => 'Sitemap', 'url' => '/sitemap']
                ]
            ]),
            'is_active' => true,
        ]);

        $menuId = $menu->id;

        // Column 1: Fashion
        $this->createFooterColumn($menuId, 1, 'Explore', [
            ['label' => 'Interior Design', 'url' => '/interior-design'],
            ['label' => 'Garden & Outdoor', 'url' => '/garden'],
            ['label' => 'Furniture', 'url' => '/furniture'],
            ['label' => 'DIY Projects', 'url' => '/diy'],
            ['label' => 'Buying Guides', 'url' => '/buying-guides'],
            ['label' => 'Seasonal Ideas', 'url' => '/seasonal']
        ]);

        // Column 2: Beauty
        $this->createFooterColumn($menuId, 2, 'Resources', [
            ['label' => 'About Us', 'url' => '/about'],
            ['label' => 'Our Team', 'url' => '/team'],
            ['label' => 'Contact', 'url' => '/contact'],
            ['label' => 'Advertise', 'url' => '/advertise'],
            ['label' => 'Write for Us', 'url' => '/contribute'],
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