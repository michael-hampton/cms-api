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
        'site_id',
        'menu_type'
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

    public function scopeHeader($query)
    {
        return $query->where('menu_type', 'header');
    }

    public function scopeFooter($query)
    {
        return $query->where('menu_type', 'footer');
    }

    public function getLayoutConfigAttribute()
    {
        $layoutConfig = $this->attributes['layout_config'];

        if (is_null($layoutConfig)) {
            return [];
        }

        if (is_string($layoutConfig)) {
            $decoded = json_decode($layoutConfig, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($layoutConfig) ? $layoutConfig : [];
    }

    public function territories($relation = false)
    {
        return $this->belongsToMany(
            Territory::class,
            'menu_territory',
            'menu_id',
            'territory_id',
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