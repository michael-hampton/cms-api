<?php

namespace App\Models;

class ProductOfferRegionSet extends Model
{
    protected $table = 'product_offer_region_sets';
    protected $fillable = [
        'region_set_id',
        'product_offer_id'
    ];
}