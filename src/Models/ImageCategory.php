<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class ImageCategory extends Model
{
    protected $table = 'image_categories';

    protected $fillable = [
        'name', 'slug', 'description', 'is_active', 'created_at', 'updated_at', 'site_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'date',
        'updated_at' => 'date'
    ];

    public function images(bool $relation = false)
    {
        return $this->belongsToMany(
            Image::class,
            'image_category_pivot',
            'category_id',
            'image_id',
            $relation
        );
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', 1);
    }

    public function scopeBySlug(QueryBuilder $query, string $slug): QueryBuilder
    {
        return $query->where('slug', $slug);
    }

    public function getImageCountAttribute(): int
    {
        return $this->images()->count();
    }
}