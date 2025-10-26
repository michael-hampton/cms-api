<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;

class RegionSet extends Model
{
    protected $table = 'region_sets';

    protected $fillable = [
        'name', 'slug', 'description', 'is_active', 'sort_order', 'site_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    protected $hidden = ['deleted_at'];

    public function territories(bool $relation = false)
    {
        return $this->hasMany(Territory::class, 'region_set_id', 'id', $relation)
            ->orderBy('sort_order');
    }

    public function pages(bool $relation = false)
    {
        return $this->hasMany(Page::class, 'region_set_id', 'id', $relation);
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', 1);
    }

    public function scopeOrdered(QueryBuilder $query): QueryBuilder
    {
        return $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    public function getTerritoryCount(): int
    {
        return Territory::where('region_set_id', $this->id)->count();
    }

    public function getPageCount(): int
    {
        return PageRegionSet::where('region_set_id', $this->id)->count();
    }

    public function toArrayWithRelations(): array
    {
        $data = $this->toArray();

        if (!$this->relationLoaded('territories')) {
            $this->load(['territories']);
        }

        return array_merge($data, [
            'territories' => $this->territories ? $this->territories->toArray() : [],
            'territory_count' => $this->getTerritoryCount(),
            'page_count' => $this->getPageCount()
        ]);
    }
}