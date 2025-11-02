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
        'sale_price',
        'override_price',
        'override_sale_price',
        'variant_sku',
        'last_price_check',
        'is_available',
        'merchant_id'
    ];

    protected $casts = [
        'price' => 'float',
        'sale_price' => 'float',
        'is_available' => 'boolean',
        'override_price' => 'boolean',
        'override_sale_price' => 'boolean',
        'last_price_check' => 'datetime',
    ];

    protected $appends = [
        'effective_price',
        'effective_sale_price',
        'effective_sku',
        'discount_percentage',
        'has_discount'
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

    /**
     * Get the effective price (either overridden or from variant)
     */
    /**
     * Get the effective regular price (either overridden or from variant)
     */
    public function getEffectivePriceAttribute(): ?float
    {

        if ($this->override_price && $this->price !== null) {
            return $this->price;
        }

        if ($this->variant) {
            return $this->variant->price;
        }

        return $this->price;
    }

    /**
     * Get the effective sale price (either overridden or from variant)
     */
    public function getEffectiveSalePriceAttribute(): ?float
    {
        if ($this->override_sale_price && $this->sale_price !== null) {
            return $this->sale_price;
        }

        if ($this->variant) {
            return $this->variant->sale_price;
        }

        return $this->sale_price ?? $this->price;
    }

    /**
     * Get the effective SKU (either overridden or from variant)
     */
    public function getEffectiveSkuAttribute(): ?string
    {
        if ($this->variant_sku) {
            return $this->variant_sku;
        }

        if ($this->variant) {
            return $this->variant->sku;
        }

        return null;
    }

    /**
     * Calculate discount percentage
     */
    public function getDiscountPercentageAttribute(): int
    {
        $effectivePrice = $this->effective_price;
        $effectiveSalePrice = $this->effective_sale_price;

        if (!$effectiveSalePrice || !$effectivePrice || $effectivePrice == 0) {
            return 0;
        }

        if ($effectiveSalePrice >= $effectivePrice) {
            return 0;
        }

        return (int) round((($effectivePrice - $effectiveSalePrice) / $effectivePrice) * 100);
    }

    /**
     * Check if merchant has an active discount
     */
    public function getHasDiscountAttribute(): bool
    {
        return $this->discount_percentage > 0;
    }

    /**
     * Get the final display price (sale price if available, otherwise regular price)
     */
    public function getFinalPriceAttribute(): float
    {
        return $this->effective_sale_price ?? $this->effective_price ?? 0;
    }
}