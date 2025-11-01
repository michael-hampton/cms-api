<?php

namespace App\Models;

class ProductMerchant extends Model
{
    protected $table = 'product_merchants';

    protected $fillable = [
        'product_id',
        'variant_id',
        'name',
        'url',
        'price',
        'last_price_check',
        'is_available',
        'merchant_id'
    ];

    protected $casts = [
        'price' => 'float',
        'is_available' => 'boolean',
        'last_price_check' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}