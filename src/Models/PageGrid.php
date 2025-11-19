<?php

namespace App\Models;

use App\Framework\Support\Str;
use App\Models\Concerns\TracksCreator;

class PageGrid extends Model
{
    use TracksCreator;

    protected $fillable = [
        'title',
        'subtitle',
        'slug',
        'layout',
        'columns',
        'show_excerpt',
        'show_image',
        'show_features',
        'show_actions',
        'items',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_at',
        'created_at',
        'updated_at',
        'start_date',
        'end_date',
        'use_hero',
        'site_id',
        'order'
    ];

    protected $casts = [
        'items' => 'array',
        'show_excerpt' => 'boolean',
        'show_image' => 'boolean',
        'show_features' => 'boolean',
        'show_actions' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'use_hero' => 'boolean'
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'start_date', 'end_date'];

    protected $hidden = [
        'deleted_at',
    ];

    protected $table = 'page_grids';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($pageGrid) {
            if (empty($pageGrid->slug)) {
                $pageGrid->slug = Str::slug($pageGrid->title);
            }
        });

        static::updating(function ($pageGrid) {
            if ($pageGrid->isDirty('title') && empty($pageGrid->slug)) {
                $pageGrid->slug = Str::slug($pageGrid->title);
            }
        });
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLayout($query, string $layout)
    {
        return $query->where('layout', $layout);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('subtitle', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");
        });
    }

    // Accessors & Mutators
    public function getPagesCountAttribute(): int
    {
        return is_array($this->items) ? count($this->items) : 0;
    }

    // Helper Methods
    public function addPage(array $pageData): void
    {
        $items = $this->items ?? [];
        $items[] = $pageData;
        $this->items = $items;
    }

    public function removePage(int $index): void
    {
        $items = $this->items ?? [];

        if (isset($items[$index])) {
            array_splice($items, $index, 1);
            $this->items = $items;
        }
    }

    public function updatePage(int $index, array $pageData): void
    {
        $items = $this->items ?? [];

        if (isset($items[$index])) {
            $items[$index] = array_merge($items[$index], $pageData);
            $this->items = $items;
        }
    }

    public function reorderPages(array $order): void
    {
        $pages = $this->items ?? [];
        $reordered = [];

        foreach ($order as $index) {
            if (isset($pages[$index])) {
                $reordered[] = $pages[$index];
            }
        }

        $this->items = $reordered;
    }

    public function addItem(array $itemData): void
    {
        $items = $this->items ?? []; // Using 'items' column for storage
        $items[] = $itemData;
        $this->items = $items;
    }

    public function removeItem(int $index): void
    {
        $items = $this->items ?? [];

        if (isset($items[$index])) {
            array_splice($items, $index, 1);
            $this->items = $items;
        }
    }

    public function updateItem(int $index, array $itemData): void
    {
        $items = $this->items ?? [];

        if (isset($items[$index])) {
            $items[$index] = array_merge($items[$index], $itemData);
            $this->items = $items;
        }
    }

    public function getItemsCountAttribute(): int
    {
        return is_array($this->items) ? count($this->items) : 0;
    }

    public function territories($relation = false)
    {
        return $this->belongsToMany(
            Territory::class,
            'page_grid_territory',
            'page_grid_id',
            'territory_id',
            $relation
        );
    }

    public function pages($relation = false)
    {
        return $this->belongsToMany(
            Page::class,
            'page_grid_pages',
            'page_grid_id',
            'page_id',
            $relation
        );
    }

    public function hasTerritory(int $territoryId): bool
    {
        return $this->territories()->where('territory_id', $territoryId)->exists();
    }

    public function syncTerritories(array $territoryIds): void
    {
        $this->territories(true)->sync($territoryIds);
    }
}