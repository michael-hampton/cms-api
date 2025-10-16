<?php

namespace App\Models;

class PageRegionSet extends Model
{
    protected $table = 'page_region_sets';

    protected $fillable = [
        'page_id',
        'region_set_id',
        'site_id'
    ];

    public function page(bool $relation = false)
    {
        return $this->belongsTo(Page::class, 'page_id', 'id', $relation);
    }

    public function regionSet(bool $relation = false)
    {
        return $this->belongsTo(RegionSet::class, 'region_set_id', 'id', $relation);
    }
}