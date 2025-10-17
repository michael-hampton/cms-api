<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;

class Tag extends Model
{
    protected $table = 'tags';
    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'usage_count',
        'is_featured',
        'meta',
        'created_at',
        'updated_at',
        'site_id',
        'seo_title',
        'seo_description',
        'no_index',
        'canonical_url'
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'is_featured' => 'boolean',
        'meta' => 'json',
        'no_index' => 'boolean'
    ];

    public static function boot()
    {
        parent::boot();

        static::created(function ($tag) {
            $tag->usage_count = 0;
            $tag->save();
        });
    }

    public function pages($relation = false)
    {
        return $this->belongsToMany(Page::class, 'page_tags', 'tag_id', 'page_id', $relation);
    }

    public function getMetaAttribute()
    {
        $rawData = $this->attributes['meta'] ?? null;
        return $rawData ? json_decode($rawData, true) : null;
    }

    public function setMetaAttribute($value): void
    {
        $this->attributes['meta'] = is_array($value) ? json_encode($value) : $value;
    }

    public function isFeatured(): bool
    {
        return (bool) $this->is_featured;
    }

    public function incrementUsage(): void
    {
        $this->usage_count = $this->usage_count + 1;
        $this->save();
    }

    public function decrementUsage(): void
    {
        if ($this->usage_count > 0) {
            $this->usage_count = $this->usage_count - 1;
            $this->exists = true;
            $this->save();
        }
    }

    public function scopeFeatured(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_featured', 1);
    }

    public function scopePopular(QueryBuilder $query, int $limit = 10): QueryBuilder
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    public function scopeBySlug(QueryBuilder $query, string $slug): QueryBuilder
    {
        return $query->where('slug', $slug);
    }
}