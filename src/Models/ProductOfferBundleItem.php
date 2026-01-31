<?php

namespace App\Models;

use Exception;

class ProductOfferBundleItem extends Model
{
    protected $table = 'product_offer_bundle_items';

    protected $fillable = [
        'bundle_id',
        'product_offer_id',
        'product_id',
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

    public function product($relation = false)
    {
        return $this->belongsTo(Product::class, 'product_id', 'id', $relation);
    }

    /**
     * Get the effective product (from offer or direct)
     */
    public function getEffectiveProduct()
    {
        if ($this->product_id) {
            return $this->product;
        }

        if ($this->product_offer_id && $this->productOffer) {
            return $this->productOffer->product;
        }

        return null;
    }

    /**
     * Get the effective price for this item
     */
    public function getEffectivePrice(): float
    {
        if ($this->product_offer_id && $this->productOffer) {
            return $this->productOffer->sale_price;
        }

        if ($this->product_id && $this->product) {
            return $this->product->price;
        }

        return 0.0;
    }

    /**
     * Get the merchant for this item
     */
    public function getEffectiveMerchant()
    {
        if ($this->product_offer_id && $this->productOffer) {
            return $this->productOffer->merchant;
        }

        if ($this->product_id && $this->product && $this->product->merchant) {
            return $this->product->merchant;
        }

        return null;
    }

    /**
     * Validate that only one of product or offer is set
     */
    public function validate(): void
    {
        $hasProduct = !empty($this->product_id);
        $hasOffer = !empty($this->product_offer_id);

        if ($hasProduct && $hasOffer) {
            throw new Exception('Bundle item cannot have both product and product offer');
        }

        if (!$hasProduct && !$hasOffer) {
            throw new Exception('Bundle item must have either product or product offer');
        }
    }
}