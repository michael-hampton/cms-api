<?php

namespace App\Models;

class ProductOfferBundleRegionSet extends Model
{
    protected $table = 'product_offer_bundle_region_sets';
    protected $fillable = [
        'region_set_id',
        'product_offer_bundle_id'
    ];
}