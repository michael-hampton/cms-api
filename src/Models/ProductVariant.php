<?php

namespace App\Models;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'attributes',
        'price_modifier',
        'is_active'
    ];

    protected $casts = [
        'attributes' => 'array',
        'price_modifier' => 'float',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }

    public function merchants()
    {
        return $this->hasMany(ProductMerchant::class, 'variant_id');
    }
}