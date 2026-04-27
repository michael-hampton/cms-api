<?php

namespace App\Database\Seeders;

use App\Framework\Database\Database;
use App\Framework\Database\Seeder\Seeder;

class MigrateEmailThemesToNewsletterBrandingConfiguration extends Seeder
{

    public function run(): void
    {
        $themes = Database::table('email_themes')->get();

        foreach ($themes as $theme) {
            $themeId = $theme->id;

            // Assemble colors map
            $colors = [];
            $colorRows = Database::table('email_theme_colors')
                ->where('theme_id', $themeId)
                ->get();
            foreach ($colorRows as $row) {
                $colors[$row->color_key] = $row->color_value;
            }

            // Assemble fonts map
            $fonts = [];
            $fontRows = Database::table('email_theme_fonts')
                ->where('theme_id', $themeId)
                ->get();
            foreach ($fontRows as $row) {
                $fonts[$row->font_key] = [
                    'family' => $row->font_family,
                    'size' => $row->font_size,
                    'weight' => $row->font_weight,
                ];
            }

            // Assemble assets map
            $assets = [];
            $assetRows = Database::table('email_theme_assets')
                ->where('theme_id', $themeId)
                ->get();
            foreach ($assetRows as $row) {
                $assets[$row->asset_key] = [
                    'type' => $row->asset_type,
                    'url' => $row->asset_url,
                    'alt' => $row->alt_text,
                    'width' => $row->width,
                    'height' => $row->height,
                ];
            }

            // Assemble settings map (cast values to their declared types)
            $settings = [];
            $settingRows = Database::table('email_theme_settings')
                ->where('theme_id', $themeId)
                ->get();
            foreach ($settingRows as $row) {
                $raw = $row->setting_value;
                $settings[$row->setting_key] = match ($row->setting_type) {
                    'boolean' => in_array(strtolower((string)$raw), ['1', 'true', 'yes'], true),
                    'number' => (int)$raw,
                    default => $raw,
                };
            }

            $themeJson = [
                'colors' => $colors,
                'fonts' => $fonts,
                'assets' => $assets,
                'settings' => $settings,
            ];

            // Clone history may be stored as JSON string or array
            $cloneHistory = $theme->clone_history ?? null;
            if (is_string($cloneHistory)) {
                $cloneHistory = json_decode($cloneHistory, true) ?? null;
            }

            Database::table('newsletter_branding_configurations')->insert([
                'newsletter_id' => null,
                'site_id' => $theme->site_id,
                'name' => $theme->name,
                'slug' => $theme->slug,
                'description' => $theme->description ?? null,
                'is_active' => (int)($theme->is_active ?? 1),
                'is_default' => (int)($theme->is_default ?? 0),
                'type' => 'email_template',
                'clone_history' => $cloneHistory ? json_encode($cloneHistory) : null,
                'theme_json' => json_encode($themeJson),
                'logo_url' => $assets['logo']['url'] ?? null,
                'header_text' => null,
                'footer_text' => null,
                'custom_css' => null,
                'created_at' => $theme->created_at ?? date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}