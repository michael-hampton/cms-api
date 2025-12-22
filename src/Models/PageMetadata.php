<?php

namespace App\Models;

class PageMetadata extends Model
{
    protected $table = 'page_metadata';
    protected $fillable = [
        'page_id', 'content_type', 'block_category', 'author', 'publish_date',
        'expiry_date', 'visibility', 'password', 'featured', 'allow_comments',
        'is_reusable_block', 'block_preview_image', 'created_at', 'updated_at'
    ];

    protected $casts = [
        'featured' => 'boolean',
        'allow_comments' => 'boolean',
        'is_reusable_block' => 'boolean',
        'publish_date' => 'date',
        'expiry_date' => 'date'
    ];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class, 'page_id', 'id');
    }

    public function getPublishDateAttribute()
    {
        $rawData = $this->attributes['publish_date'] ?? null;
        return $rawData ? date('Y-m-d H:i:s', strtotime($rawData)) : null;
    }

    public function getExpiryDateAttribute()
    {
        $rawData = $this->attributes['expiry_date'] ?? null;
        return $rawData ? date('Y-m-d H:i:s', strtotime($rawData)) : null;
    }

    public function isFeatured(): bool
    {
        return (bool) $this->featured;
    }

    public function isExpired(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return strtotime($this->expiry_date) < time();
    }

    public function getAccessLevel(): string
    {
        // Map old visibility to new access levels
        if ($this->visibility === 'public') {
            return 'free';
        } elseif ($this->visibility === 'private') {
            return 'member'; // or 'premium' based on your logic
        }

        return $this->visibility ?? 'free';
    }
}