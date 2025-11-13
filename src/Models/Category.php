<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Concerns\HasCloneHistory;

class Category extends Model
{
    use HasCloneHistory;

    protected $table = 'categories';
    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'parent_id',
        'sort_order',
        'is_active',
        'meta',
        'created_at',
        'updated_at',
        'site_id',
        'seo_title',
        'seo_description',
        'no_index',
        'canonical_url',
        'clone_history'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'meta' => 'json',
        'no_index' => 'boolean',
        'clone_history' => 'array',
    ];

    public function parent(): ?Model
    {
        if (!$this->parent_id) {
            return null;
        }
        return Category::find($this->parent_id);
    }

    public function children(): Collection
    {
        return $this->hasMany(Category::class, 'parent_id', 'id');
    }

    public function pages(): Collection
    {
        return $this->belongsToMany(Page::class, 'page_categories', 'category_id', 'page_id');
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

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function hasParent(): bool
    {
        return $this->parent_id !== null;
    }

    public function isRootCategory(): bool
    {
        return $this->parent_id === null;
    }

    public function getPageCount(): int
    {
        return $this->pages()->count();
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', 1);
    }

    public function scopeRoots(QueryBuilder $query): QueryBuilder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeBySlug(QueryBuilder $query, string $slug): QueryBuilder
    {
        return $query->where('slug', $slug);
    }

    public function scopeOrdered(QueryBuilder $query): QueryBuilder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    public function getBreadcrumb(): array
    {
        $breadcrumb = [];
        $current = $this;

        while ($current) {
            array_unshift($breadcrumb, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug
            ]);
            $current = $current->parent();
        }

        return $breadcrumb;
    }
}