<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class Author extends Model
{
    protected $table = 'authors';

    protected $fillable = [
        'name', 'slug', 'email', 'bio', 'avatar', 'website',
        'twitter', 'linkedin', 'facebook', 'status', 'site_id'
    ];

    protected $casts = [
        'created_at' => 'date',
        'updated_at' => 'date'
    ];

    public function pages($relation = false)
    {
        return $this->belongsToMany(Page::class, 'page_authors', 'author_id', 'id', $relation);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function getUrlAttribute(): string
    {
        return '/authors/' . $this->slug;
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'active');
    }

    public function scopeBySlug(QueryBuilder $query, string $slug): QueryBuilder
    {
        return $query->where('slug', $slug);
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['url'] = $this->getUrlAttribute();
        return $data;
    }
}