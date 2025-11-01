<?php

namespace App\Models;

class Merchant extends Model
{
    protected $table = 'merchants';
    protected $fillable = ['name', 'slug'];

    public function productMerchants()
    {
        return $this->hasMany(ProductMerchant::class);
    }

    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            ProductMerchant::class,
            'merchant_id',
            'id',
            'id',
            'product_id'
        );
    }
}