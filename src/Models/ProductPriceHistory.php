<?php

namespace App\Models;

class ProductPriceHistory extends Model
{
    protected $table = 'product_price_history';

    protected $fillable = [
        'product_id',
        'merchant_id',
        'price',
        'sale_price',
        'recorded_at'
    ];

    protected $casts = [
        'price' => 'float',
        'sale_price' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function merchant()
    {
        return $this->belongsTo(ProductMerchant::class);
    }
}