<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class EmailThemeResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'slug' => $this->getAttribute('slug'),
            'description' => $this->getAttribute('description'),
            'is_active' => $this->getAttribute('is_active'),
            'is_default' => $this->getAttribute('is_default'),
            'site_id' => $this->getAttribute('site_id'),
            'colors' => $this->getColors(),
            'fonts' => $this->getFonts(),
            'assets' => $this->getAssets(),
            'settings' => $this->getSettings(),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at')
        ];
    }

    private function getColors()
    {
        if (empty($this->getAttribute('colors'))) {
            return [];
        }

        $colors = is_array($this->getAttribute('colors')) ? collect($this->getAttribute('colors')) : $this->getAttribute('colors');

        return $colors->keyBy('color_key')->pluck('color_value');

    }

    private function getFonts()
    {
        if (empty($this->getAttribute('fonts'))) {
            return [];
        }

        $fonts = is_array($this->getAttribute('fonts')) ? collect($this->getAttribute('fonts')) : $this->getAttribute('fonts');

        return $fonts->keyBy('font_key')->map(function ($font) {
            return [
                'family' => $this->getAttribute('font_family'),
                'size' => $this->getAttribute('font_size'),
                'weight' => $this->getAttribute('font_weight')
            ];
        });

    }

    private function getAssets()
    {
        if (empty($this->getAttribute('assets'))) {
            return [];
        }

        $assets = is_array($this->getAttribute('assets')) ? collect($this->getAttribute('assets')) : $this->getAttribute('assets');

        return $assets->keyBy('asset_key');
    }

    private function getSettings()
    {
        if (empty($this->getAttribute('settings'))) {
            return [];
        }

        $assets = is_array($this->getAttribute('settings')) ? collect($this->getAttribute('settings')) : $this->getAttribute('settings');

        return $assets->keyBy('setting_key')->pluck('setting_value');
    }
}