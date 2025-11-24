<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\EmailTheme;
use App\Models\EmailThemeColor;
use App\Models\EmailThemeFont;

class EmailThemeSeeder extends Seeder
{
    public function run(): void
    {
        // Get all sites
        $sites = \App\Models\Site::all();

        foreach ($sites as $site) {
            $theme = EmailTheme::create([
                'name' => 'Default Theme',
                'slug' => 'default',
                'description' => 'Default email theme for ' . $site->name,
                'is_active' => true,
                'is_default' => true,
                'site_id' => $site->id
            ]);

            // Default colors
            $colors = [
                'primary' => '#667eea',
                'secondary' => '#764ba2',
                'success' => '#4CAF50',
                'warning' => '#ffc107',
                'danger' => '#f44336',
                'text' => '#333333',
                'text_light' => '#6c757d',
                'background' => '#f6f6f6',
                'card_background' => '#ffffff',
                'border' => '#e9ecef',
                'link' => '#3498db'
            ];

            foreach ($colors as $key => $value) {
                EmailThemeColor::create([
                    'theme_id' => $theme->id,
                    'color_key' => $key,
                    'color_value' => $value
                ]);
            }

            // Default fonts
            $fonts = [
                'body' => [
                    'family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
                    'size' => '16px',
                    'weight' => '400'
                ],
                'heading' => [
                    'family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
                    'size' => '24px',
                    'weight' => '600'
                ],
                'button' => [
                    'family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
                    'size' => '16px',
                    'weight' => 'bold'
                ]
            ];

            foreach ($fonts as $key => $font) {
                EmailthemeFont::create([
                    'theme_id' => $theme->id,
                    'font_key' => $key,
                    'font_family' => $font['family'],
                    'font_size' => $font['size'],
                    'font_weight' => $font['weight']
                ]);
            }

            // Default settings
            $settings = [
                'max_width' => ['value' => '600', 'type' => 'number'],
                'padding' => ['value' => '20', 'type' => 'number'],
                'border_radius' => ['value' => '8', 'type' => 'number'],
                'header_gradient' => ['value' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 'type' => 'string'],
                'show_footer' => ['value' => '1', 'type' => 'boolean'],
                'show_social_links' => ['value' => '1', 'type' => 'boolean']
            ];

            foreach ($settings as $key => $setting) {
                \App\Models\EmailThemeSetting::create([
                    'theme_id' => $theme->id,
                    'setting_key' => $key,
                    'setting_value' => $setting['value'],
                    'setting_type' => $setting['type']
                ]);
            }
        }
    }
}