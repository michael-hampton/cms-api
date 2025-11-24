<?php
// src/Models/EmailTheme.php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Models\Concerns\HasCloneHistory;

class EmailTheme extends Model
{
    use HasCloneHistory;

    protected $table = 'email_themes';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'is_default',
        'site_id',
        'clone_history',
        'created_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'clone_history' => 'array',
    ];

    private ?array $colorsCache = null;
    private ?array $fontsCache = null;
    private ?array $assetsCache = null;
    private ?array $settingsCache = null;

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function getColor(string $key, string $default = '#000000'): string
    {
        $colors = $this->getColors();
        return $colors[$key] ?? $default;
    }

    public function getColors(): array
    {
        if ($this->colorsCache === null) {
            $colors = $this->colors();
            $this->colorsCache = [];
            foreach ($colors as $color) {
                $this->colorsCache[$color->color_key] = $color->color_value;
            }
        }
        return $this->colorsCache;
    }

    public function colors()
    {
        return $this->hasMany(EmailThemeColor::class, 'theme_id', 'id');
    }

    public function getFont(string $key): ?array
    {
        $fonts = $this->getFonts();
        return $fonts[$key] ?? null;
    }

    public function getFonts(): array
    {
        if ($this->fontsCache === null) {
            $fonts = $this->fonts();
            $this->fontsCache = [];
            foreach ($fonts as $font) {
                $this->fontsCache[$font->font_key] = [
                    'family' => $font->font_family,
                    'size' => $font->font_size,
                    'weight' => $font->font_weight
                ];
            }
        }
        return $this->fontsCache;
    }

    public function fonts()
    {
        return $this->hasMany(EmailThemeFont::class, 'theme_id', 'id');
    }

    public function getAsset(string $key): ?array
    {
        $assets = $this->getAssets();
        return $assets[$key] ?? null;
    }

    public function getAssets(): array
    {
        if ($this->assetsCache === null) {
            $assets = $this->assets();
            $this->assetsCache = [];
            foreach ($assets as $asset) {
                $this->assetsCache[$asset->asset_key] = [
                    'type' => $asset->asset_type,
                    'url' => $asset->asset_url,
                    'alt' => $asset->alt_text,
                    'width' => $asset->width,
                    'height' => $asset->height
                ];
            }
        }
        return $this->assetsCache;
    }

    public function assets()
    {
        return $this->hasMany(EmailThemeAsset::class, 'theme_id', 'id');
    }

    public function getSetting(string $key, $default = null)
    {
        $settings = $this->getSettings();
        return $settings[$key] ?? $default;
    }

    public function getSettings(): array
    {
        if ($this->settingsCache === null) {
            $settings = $this->settings();
            $this->settingsCache = [];
            foreach ($settings as $setting) {
                $value = $setting->setting_value;
                if ($setting->setting_type === 'number') {
                    $value = (int)$value;
                } elseif ($setting->setting_type === 'boolean') {
                    $value = (bool)$value;
                }
                $this->settingsCache[$setting->setting_key] = $value;
            }
        }
        return $this->settingsCache;
    }

    public function settings()
    {
        return $this->hasMany(EmailThemeSetting::class, 'theme_id', 'id');
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_default', true);
    }

    public function scopeBySlug(QueryBuilder $query, string $slug): QueryBuilder
    {
        return $query->where('slug', $slug);
    }

    public function scopeBySite(QueryBuilder $query, int $siteId): QueryBuilder
    {
        return $query->where('site_id', $siteId);
    }
}