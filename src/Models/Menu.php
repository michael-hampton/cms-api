<?php

namespace App\Models;

class Menu extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'layout_config',
        'is_active',
    ];

    protected $casts = [
        'layout_config' => 'json',
        'is_active' => 'boolean',
    ];

    protected $table = 'menus';

    public function items()
    {
        return $this->hasMany(MenuItem::class, null, null, true)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function allItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function getActiveItemsAttribute()
    {
        return $this->items()->where('is_active', true)->with(['activeChildren'])->get();
    }
}