<?php
// src/Actions/CloneEmailTheme.php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Framework\Support\Str;
use App\Models\EmailTheme;
use App\Models\EmailThemeAsset;
use App\Models\EmailThemeColor;
use App\Models\EmailThemeFont;
use App\Models\EmailThemeSetting;
use App\Services\ImageUploadService;

class CloneEmailTheme
{
    public function __construct(
        private Database           $db,
        private ImageUploadService $imageUploadService
    )
    {
    }

    public function handle(int $themeId, ?string $newName = null): array
    {
        return $this->db->transaction(function () use ($themeId, $newName) {
            $original = EmailTheme::find($themeId);

            if (!$original) {
                throw new \Exception("Email theme with ID {$themeId} not found");
            }

            // Determine new name
            $cloneName = $newName ?? $original->name . ' (Copy)';

            // Generate unique slug
            $baseSlug = Str::slug($cloneName);
            $slug = $this->generateUniqueSlug($baseSlug, $original->site_id);

            // Clone theme
            $cloned = EmailTheme::create([
                'name' => $cloneName,
                'slug' => $slug,
                'description' => $original->description,
                'is_active' => false, // Start as inactive
                'is_default' => false, // Never clone as default
                'site_id' => $original->site_id,
                'created_by' => $original->created_by
            ]);

            // Clone colors
            $colors = EmailThemeColor::where('theme_id', $original->id)->get();
            foreach ($colors as $color) {
                EmailThemeColor::create([
                    'theme_id' => $cloned->id,
                    'color_key' => $color->color_key,
                    'color_value' => $color->color_value
                ]);
            }

            // Clone fonts
            $fonts = EmailThemeFont::where('theme_id', $original->id)->get();
            foreach ($fonts as $font) {
                EmailThemeFont::create([
                    'theme_id' => $cloned->id,
                    'font_key' => $font->font_key,
                    'font_family' => $font->font_family,
                    'font_size' => $font->font_size,
                    'font_weight' => $font->font_weight
                ]);
            }

            // Clone assets (including logo)
            $assets = EmailThemeAsset::where('theme_id', $original->id)->get();
            foreach ($assets as $asset) {
                $newAssetUrl = $asset->asset_url;

                // If asset is a logo, duplicate the file
                if ($asset->asset_key === 'logo' && $asset->asset_url) {
                    try {
                        $newAssetUrl = $this->imageUploadService->duplicate($asset->asset_url);
                    } catch (\Exception $e) {
                        // If duplication fails, keep original URL
                    }
                }

                EmailThemeAsset::create([
                    'theme_id' => $cloned->id,
                    'asset_key' => $asset->asset_key,
                    'asset_type' => $asset->asset_type,
                    'asset_url' => $newAssetUrl,
                    'alt_text' => $asset->alt_text,
                    'width' => $asset->width,
                    'height' => $asset->height
                ]);
            }

            // Clone settings
            $settings = EmailThemeSetting::where('theme_id', $original->id)->get();
            foreach ($settings as $setting) {
                EmailThemeSetting::create([
                    'theme_id' => $cloned->id,
                    'setting_key' => $setting->setting_key,
                    'setting_value' => $setting->setting_value,
                    'setting_type' => $setting->setting_type
                ]);
            }

            // Add clone history
            $cloneHistory = $original->clone_history ?? [];
            $cloneHistory[] = [
                'cloned_from_id' => $original->id,
                'cloned_at' => date('Y-m-d H:i:s'),
                'cloned_by' => auth()->id() ?? null
            ];
            $cloned->update(['clone_history' => $cloneHistory]);

            return [
                'success' => true,
                'theme' => $cloned->fresh()->toArray(),
                'message' => 'Email theme duplicated successfully'
            ];
        });
    }

    private function generateUniqueSlug(string $baseSlug, int $siteId, int $counter = 0): string
    {
        $slug = $counter === 0 ? $baseSlug : "{$baseSlug}-{$counter}";

        $exists = EmailTheme::where('slug', $slug)
            ->where('site_id', $siteId)
            ->exists();

        if ($exists) {
            return $this->generateUniqueSlug($baseSlug, $siteId, $counter + 1);
        }

        return $slug;
    }
}