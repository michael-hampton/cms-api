<?php

namespace App\Models;

use App\Models\Concerns\HasCloneHistory;
use App\Models\Concerns\TracksCreator;

class Brand extends Model
{
    use HasCloneHistory, TracksCreator;

    protected $table = 'brands';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'website',
        'is_active',
        'site_id',
        'seo_title',
        'seo_description',
        'no_index',
        'canonical_url',
        'clone_history'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'no_index' => 'boolean',
        'clone_history' => 'array',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}