<?php

namespace App\Models;

use App\Framework\Support\Str;

class PageGrid extends Model
{
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
        'pages',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_at',
        'created_at',
        'updated_at',
        'start_date',
        'end_date',
        'use_hero'
    ];

    protected $casts = [
        'pages' => 'array',
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
        return is_array($this->pages) ? count($this->pages) : 0;
    }

    // Helper Methods
    public function addPage(array $pageData): void
    {
        $pages = $this->pages ?? [];
        $pages[] = $pageData;
        $this->pages = $pages;
    }

    public function removePage(int $index): void
    {
        $pages = $this->pages ?? [];

        if (isset($pages[$index])) {
            array_splice($pages, $index, 1);
            $this->pages = $pages;
        }
    }

    public function updatePage(int $index, array $pageData): void
    {
        $pages = $this->pages ?? [];

        if (isset($pages[$index])) {
            $pages[$index] = array_merge($pages[$index], $pageData);
            $this->pages = $pages;
        }

        // REMOVED: $this->pages = json_encode($this->pages ?? []);
        // The casting system handles JSON encoding automatically
    }

    public function reorderPages(array $order): void
    {
        $pages = $this->pages ?? [];
        $reordered = [];

        foreach ($order as $index) {
            if (isset($pages[$index])) {
                $reordered[] = $pages[$index];
            }
        }

        $this->pages = $reordered;
    }
}