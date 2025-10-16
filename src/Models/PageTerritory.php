<?php

namespace App\Models;

class PageTerritory extends Model
{
    protected $table = 'page_territories';

    protected $fillable = [
        'page_id',
        'territory_id',
        'site_id'
    ];

    public function page(bool $relation = false)
    {
        return $this->belongsTo(Page::class, 'page_id', 'id', $relation);
    }

    public function territory(bool $relation = false)
    {
        return $this->belongsTo(Territory::class, 'territory_id', 'id', $relation);
    }
}