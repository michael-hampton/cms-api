<?php

namespace App\Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;

class SeedFoodFooterMenu
{
    public function run(): void
    {
        $siteId = 5;

        $menu = Menu::create([
            'name' => 'Food Footer Menu',
            'site_id' => $siteId,
            'menu_type' => 'footer',
            'slug' => 'food-footer-menu',
            'layout_config' => json_encode([
                'footer_style' => 'modern',
                'max_columns' => 4,
                'show_brand_section' => true,
                'logo_type' => 'icon',
                'logo_icon' => '🍽️',
                'brand_name' => 'Taste & Table',
                'footer_description' => 'Your trusted source for delicious recipes, expert cooking tips, honest product reviews, and culinary inspiration. Join our community of passionate home cooks and food lovers.',
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
                'newsletter_description' => 'Get weekly recipes, cooking tips, and exclusive deals delivered to your inbox every Friday.',
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
        $this->createFooterColumn($menuId, 1, 'Recipes', [
            ['label' => 'Breakfast', 'url' => '/recipes/breakfast'],
            ['label' => 'Lunch', 'url' => '/recipes/lunch'],
            ['label' => 'Dinner', 'url' => '/recipes/dinner'],
            ['label' => 'Desserts', 'url' => '/recipes/dessert'],
            ['label' => 'Quick & Easy', 'url' => '/recipes/quick-easy'],
            ['label' => 'Healthy', 'url' => '/recipes/healthy']
        ]);

        // Column 2: Beauty
        $this->createFooterColumn($menuId, 2, 'Resources', [
            ['label' => 'About Us', 'url' => '/about'],
            ['label' => 'Our Chefs', 'url' => '/team'],
            ['label' => 'Contact', 'url' => '/contact'],
            ['label' => 'Cooking Classes', 'url' => '/events'],
            ['label' => 'Submit Recipe', 'url' => '/contribute'],
            ['label' => 'FAQ', 'url' => '/faq']
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