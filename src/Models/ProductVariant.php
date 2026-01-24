<?php

namespace App\Models;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'attributes',
        'price',
        'sale_price',
        'price_modifier',
        'is_active',
        'created_at',
        'updated_at',
    ];


    protected $casts = [
        'attributes' => 'array',
        'price' => 'float',
        'sale_price' => 'float',
        'price_modifier' => 'float',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['name', 'final_price', 'discount_percentage'];

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

    // Add computed attribute for final price (can keep for backward compatibility)
    public function getFinalPriceAttribute(): float
    {
        return $this->price ?? ($this->product->price + ($this->price_modifier ?? 0));
    }

// Add discount percentage
    public function getDiscountPercentageAttribute(): int
    {
        if (!$this->sale_price || $this->price == 0 || $this->sale_price > $this->price) {
            return 0;
        }
        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }
}