<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Menu;
use App\Models\MenuItem;

class SeedFashionFooterMenu extends Seeder
{

    public function run(): void
    {
        $siteId = 4;

        $menu = Menu::create([
            'name' => 'Fashion Footer Menu',
            'site_id' => $siteId,
            'menu_type' => 'footer',
            'slug' => 'fashion-footer-menu',
            'layout_config' => json_encode([
                'footer_style' => 'fashion',
                'max_columns' => 4,
                'show_brand_section' => true,
                'logo_type' => 'text',
                'logo_main' => 'VOGUE',
                'logo_sub' => 'NOIR',
                'footer_description' => 'Fashion Forward. Always.',
                'social_style' => 'simple',
                'social_links' => [
                    'instagram' => '#',
                    'twitter' => '#',
                    'pinterest' => '#',
                    'facebook' => '#'
                ],
                'show_newsletter' => true,
                'newsletter_title' => 'Newsletter',
                'newsletter_description' => 'Get the latest fashion news delivered to your inbox.',
                'newsletter_placeholder' => 'Your email',
                'newsletter_button_text' => 'Subscribe',
                'newsletter_action' => '/newsletter/subscribe',
                'brand_name' => 'VOGUE NOIR',
                'copyright_text' => '© {year} {brand}. All rights reserved.',
                'legal_links' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy'],
                    ['label' => 'Terms of Service', 'url' => '/terms'],
                    ['label' => 'Cookie Policy', 'url' => '/cookies']
                ]
            ]),
            'is_active' => true,
        ]);

        $menuId = $menu->id;

        // Column 1: Fashion
        $this->createFooterColumn($menuId, 1, 'Fashion', [
            ['label' => 'Runway', 'url' => '/runway'],
            ['label' => 'Street Style', 'url' => '/street-style'],
            ['label' => 'Trends', 'url' => '/trends'],
            ['label' => 'Designers', 'url' => '/designers']
        ]);

        // Column 2: Beauty
        $this->createFooterColumn($menuId, 2, 'Beauty', [
            ['label' => 'Makeup', 'url' => '/makeup'],
            ['label' => 'Skincare', 'url' => '/skincare'],
            ['label' => 'Hair', 'url' => '/hair'],
            ['label' => 'Reviews', 'url' => '/reviews']
        ]);

        // Column 3: About
        $this->createFooterColumn($menuId, 3, 'About', [
            ['label' => 'About Us', 'url' => '/about'],
            ['label' => 'Contact', 'url' => '/contact'],
            ['label' => 'Advertise', 'url' => '/advertise'],
            ['label' => 'Careers', 'url' => '/careers']
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