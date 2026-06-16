<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Site;

final class GenericSiteFooterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Site::all() as $site) {
            $existing = Menu::where('site_id', (int) $site->id)
                ->where('menu_type', 'footer')
                ->first();

            if ($existing) {
                continue;
            }

            $menu = Menu::create([
                'name' => $site->name . ' Footer',
                'slug' => $site->slug . '-footer',
                'description' => 'Generic footer generated for ' . $site->name,
                'site_id' => (int) $site->id,
                'menu_type' => 'footer',
                'is_active' => true,
                'layout_config' => [
                    'footer_style' => 'modern',
                    'max_columns' => 4,
                    'show_brand_section' => true,
                    'logo_type' => 'text',
                    'logo_main' => (string) $site->name,
                    'brand_name' => (string) $site->name,
                    'footer_description' => 'Latest stories, guides and updates from ' . $site->name . '.',
                    'social_style' => 'simple',
                    'social_links' => array_filter([
                        'facebook' => $site->facebook_url,
                        'instagram' => $site->instagram_url,
                        'twitter' => $site->twitter_url,
                        'linkedin' => $site->linkedin_url,
                    ]),
                    'show_newsletter' => true,
                    'newsletter_title' => 'Stay informed',
                    'newsletter_description' => 'Get the latest updates delivered to your inbox.',
                    'newsletter_placeholder' => 'Your email address',
                    'newsletter_button_text' => 'Subscribe',
                    'copyright_text' => '© {year} {brand}. All rights reserved.',
                    'legal_links' => [
                        ['label' => 'Privacy', 'url' => '/' . $site->slug . '/privacy'],
                        ['label' => 'Terms', 'url' => '/' . $site->slug . '/terms'],
                        ['label' => 'Cookies', 'url' => '/' . $site->slug . '/cookies'],
                    ],
                ],
            ]);

            $this->createColumn((int) $menu->id, 1, 'Explore', [
                ['Home', '/' . $site->slug],
                ['Authors', '/' . $site->slug . '/authors'],
                ['Categories', '/' . $site->slug . '/categories'],
                ['Tags', '/' . $site->slug . '/tags'],
            ]);

            $this->createColumn((int) $menu->id, 2, 'Company', [
                ['About', '/' . $site->slug . '/about'],
                ['Contact', '/' . $site->slug . '/contact'],
            ]);

            $this->createColumn((int) $menu->id, 3, 'Support', [
                ['Privacy', '/' . $site->slug . '/privacy'],
                ['Terms', '/' . $site->slug . '/terms'],
                ['Cookies', '/' . $site->slug . '/cookies'],
            ]);
        }
    }

    private function createColumn(int $menuId, int $column, string $heading, array $links): void
    {
        MenuItem::create([
            'menu_id' => $menuId,
            'label' => $heading,
            'column_group' => $column,
            'sort_order' => 0,
            'is_active' => true,
            'target_type' => 'custom',
            'custom_url' => '#',
        ]);

        foreach ($links as $index => [$label, $url]) {
            MenuItem::create([
                'menu_id' => $menuId,
                'label' => $label,
                'column_group' => $column,
                'sort_order' => $index + 1,
                'is_active' => true,
                'target_type' => 'custom',
                'custom_url' => $url,
            ]);
        }
    }
}
