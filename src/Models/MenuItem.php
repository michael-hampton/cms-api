<?php

namespace App\Models;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'target_type',
        'target_id',
        'custom_url',
        'css_class',
        'icon',
        'attributes',
        'open_in_new_tab',
        'sort_order',
        'is_active',
        'column_group'
    ];

    protected $casts = [
        'attributes' => 'array',
        'open_in_new_tab' => 'boolean',
        'is_active' => 'boolean',
        'column_group' => 'integer',
    ];

    protected $table = 'menu_items';

    protected $appends = ['url', 'target_data'];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function activeChildren()
    {
        return $this->hasMany(MenuItem::class, 'parent_id', null, true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['activeChildren'])->get();
    }

    public function getUrlAttribute(): string
    {
        switch ($this->target_type) {
            case 'page':
                return $this->getPageUrl();
            case 'category':
                return $this->getCategoryUrl();
            case 'custom':
            case 'external':
                return $this->custom_url ?? '#';
            default:
                return '#';
        }
    }

    public function getTargetDataAttribute()
    {
        switch ($this->target_type) {
            case 'page':
                return $this->getPageData();
            case 'category':
                return $this->getCategoryData();
            default:
                return null;
        }
    }

    private function getPageUrl(): string
    {
        if ($this->target_id && class_exists('App\Models\Page')) {
            $page = Page::find($this->target_id);
            return $page ? $page->slug : '#';
        }
        return '#';
    }

    private function getCategoryUrl(): string
    {
        if ($this->target_id && class_exists('App\Models\Category')) {
            $category = Category::find($this->target_id);
            return $category ? "/category/{$category->slug}" : '#';
        }
        return '#';
    }

    private function getPageData()
    {
        if ($this->target_id && class_exists('App\Models\Page')) {
            $page = Page::find($this->target_id);
            return $page ? [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'image' => $page->featured_image ?? null,
                'excerpt' => $page->excerpt ?? null,
            ] : null;
        }
        return null;
    }

    private function getCategoryData()
    {
        if ($this->target_id && class_exists('App\Models\Category')) {
            $category = Category::find($this->target_id);
            return $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'image' => $category->image ?? null,
                'description' => $category->description ?? null,
            ] : null;
        }
        return null;
    }

    public function getDepthAttribute(): int
    {
        $depth = 0;
        $parent = $this->parent;
        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }
        return $depth;
    }
}