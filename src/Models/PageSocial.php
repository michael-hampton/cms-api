<?php

namespace App\Models;

class PageSocial extends Model
{
    protected $table = 'page_social';
    protected $fillable = [
        'page_id', 'enable_sharing', 'platforms', 'share_text', 'share_hashtags',
        'share_via', 'platform_overrides', 'track_shares', 'track_clicks', 'pixel_ids', 'gtm_events',
        'show_follower_count', 'show_share_count', 'show_recent_activity',
        'testimonial_integration', 'auto_embed_links', 'embed_width',
        'embed_height', 'lazy_load_embeds', 'created_at', 'updated_at'
    ];

    protected $casts = [
        'enable_sharing' => 'boolean',
        'platforms' => 'json',
        'platform_overrides' => 'json',
        'track_shares' => 'boolean',
        'track_clicks' => 'boolean',
        'pixel_ids' => 'json',
        'gtm_events' => 'boolean',
        'show_follower_count' => 'boolean',
        'show_share_count' => 'boolean',
        'show_recent_activity' => 'boolean',
        'testimonial_integration' => 'boolean',
        'auto_embed_links' => 'boolean',
        'lazy_load_embeds' => 'boolean'
    ];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class, 'page_id', 'id');
    }

    public function getPlatformsAttribute()
    {
        $rawData = $this->attributes['platforms'] ?? null;
        return $rawData ? json_decode($rawData, true) : [];
    }

    public function setPlatformsAttribute($value): void
    {
        $this->attributes['platforms'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getPlatformOverridesAttribute()
    {
        $rawData = $this->attributes['platform_overrides'] ?? null;
        return $rawData ? json_decode($rawData, true) : [];
    }

    public function getPixelIdsAttribute()
    {
        $rawData = $this->attributes['pixel_ids'] ?? null;
        return $rawData ? json_decode($rawData, true) : [];
    }

    public function setPixelIdsAttribute($value): void
    {
        $this->attributes['pixel_ids'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setPlatformOverridesAttribute($value): void
    {
        $this->attributes['platform_overrides'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Get platform-specific override or fall back to default
     */
    public function getPlatformContent(string $platform, string $defaultTitle = '', string $defaultDescription = '', string $defaultImage = ''): array
    {
        $overrides = $this->platform_overrides;

        if (isset($overrides[$platform])) {
            $override = $overrides[$platform];
            return [
                'title' => $override['title'] ?? $defaultTitle,
                'description' => $override['description'] ?? $defaultDescription,
                'imageUrl' => $override['imageUrl'] ?? $defaultImage,
                'imageId' => $override['imageId'] ?? null
            ];
        }

        return [
            'title' => $defaultTitle,
            'description' => $defaultDescription,
            'imageUrl' => $defaultImage,
            'imageId' => null
        ];
    }
}