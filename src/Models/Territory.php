<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Models\Concerns\HasCloneHistory;

class Territory extends Model
{
    use HasCloneHistory;

    protected $table = 'territories';

    protected $fillable = [
        'name',
        'code',
        'region_set_id',
        'is_active',
        'sort_order',
        'site_id',
        'slug',
        'clone_history'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'region_set_id' => 'integer',
        'clone_history' => 'array',
    ];

    protected $hidden = ['deleted_at'];

    public function regionSet(bool $relation = false)
    {
        return $this->belongsTo(RegionSet::class, 'region_set_id', 'id', $relation);
    }

    public function pages($relation = false)
    {
        return $this->belongsToMany(Page::class, 'page_territories', 'territory_id', 'id', $relation);
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

    public function scopeByRegionSet(QueryBuilder $query, int $regionSetId): QueryBuilder
    {
        return $query->where('region_set_id', $regionSetId);
    }

    public function getPageCount(): int
    {
        return PageTerritory::where('territory_id', $this->id)->count();
    }

    public function toArrayWithRelations(): array
    {
        $data = $this->toArray();

        if (!$this->relationLoaded('regionSet')) {
            $this->load(['regionSet']);
        }

        return array_merge($data, [
            'region_set' => $this->regionSet ? $this->regionSet->toArray() : null,
            'page_count' => $this->getPageCount()
        ]);
    }

    public function menus(bool $relation = false)
    {
        return $this->belongsToMany(
            Menu::class,
            'menu_territory',
            'territory_id',
            'menu_id',
            $relation
        )->withTimestamps();
    }

    public function pageGrids(bool $relation = false)
    {
        return $this->belongsToMany(
            PageGrid::class,
            'page_grid_territory',
            'territory_id',
            'page_grid_id',
            $relation
        )->withTimestamps();
    }

    public function getMenuCount(): int
    {
        return MenuTerritory::where('territory_id', $this->id)->count();
    }

    public function getPageGridCount(): int
    {
        return PageGridPage::where('territory_id', $this->id)->get()->count();
    }

    public function members($relation = false)
    {
        return $this->hasMany(Member::class, 'territory_id', 'id', $relation);
    }
}