<?php

namespace App\Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;

class SeedTechFooterMenu
{
    public function run(): void
    {
        $siteId = 2;

        $menu = Menu::create([
            'name' => 'Tech Footer Menu',
            'site_id' => $siteId,
            'menu_type' => 'footer',
            'slug' => 'tech-footer-menu',
            'layout_config' => json_encode([
                'footer_style' => 'tech',
                'max_columns' => 4,
                'show_brand_section' => true,
                'logo_type' => 'icon',
                'logo_icon' => '⚡',
                'brand_name' => 'TechWeekly',
                'footer_description' => 'Empowering developers and tech enthusiasts with cutting-edge insights.',
                'social_style' => 'icons-svg',
                'social_links' => [
                    'twitter' => '#',
                    'github' => '#',
                    'linkedin' => '#'
                ],
                'show_newsletter' => true,
                'newsletter_title' => 'Newsletter',
                'newsletter_description' => 'Get weekly tech insights delivered to your inbox.',
                'newsletter_placeholder' => 'Your email',
                'newsletter_button_text' => 'Subscribe',
                'newsletter_action' => '/newsletter/subscribe',
                'copyright_text' => '© {year} {brand}. All rights reserved.',
                'legal_links' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy'],
                    ['label' => 'Terms of Service', 'url' => '/terms'],
                    ['label' => 'Code of Conduct', 'url' => '/code-of-conduct']
                ]
            ]),
            'is_active' => true,
        ]);

        $menuId = $menu->id;

        // Column 1: Fashion
        $this->createFooterColumn($menuId, 1, 'Technology', [
            ['label' => 'Artificial Intelligence', 'url' => '/ai'],
            ['label' => 'Cloud Computing', 'url' => '/cloud'],
            ['label' => 'Hardware', 'url' => '/hardware'],
            ['label' => 'Software', 'url' => '/software']
        ]);

        // Column 2: Beauty
        $this->createFooterColumn($menuId, 2, 'Development', [
            ['label' => 'Programming', 'url' => '/programming'],
            ['label' => 'Web Development', 'url' => '/web-dev'],
            ['label' => 'Mobile Dev', 'url' => '/mobile'],
            ['label' => 'DevOps', 'url' => '/devops']
        ]);

        // Column 3: About
        $this->createFooterColumn($menuId, 3, 'Resources', [
            ['label' => 'Tutorials', 'url' => '/tutorials'],
            ['label' => 'Reviews', 'url' => '/reviews'],
            ['label' => 'About Us', 'url' => '/about'],
            ['label' => 'Contact', 'url' => '/contact']
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