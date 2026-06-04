<?php

namespace App\Models;

class ProductRegionSet extends Model
{
    protected $table = 'product_region_sets';

    protected $fillable = [
        'product_id',
        'region_set_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function regionSet()
    {
        return $this->belongsTo(RegionSet::class);
    }
}