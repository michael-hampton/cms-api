<?php

namespace App\Models;

class ProductOfferBundleItem extends Model
{
    protected $table = 'product_offer_bundle_items';

    protected $fillable = [
        'bundle_id',
        'product_offer_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function bundle($relation = false)
    {
        return $this->belongsTo(ProductOfferBundle::class, 'bundle_id', 'id', $relation);
    }

    public function productOffer($relation = false)
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id', 'id', $relation);
    }
}