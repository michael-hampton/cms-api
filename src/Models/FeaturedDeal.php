<?php
namespace App\Models;

class FeaturedDeal extends Model
{
    protected $table = 'featured_deals';

    protected $fillable = [
        'product_id', 'variant_id', 'merchant_id', 'site_id',
        'featured_date', 'position', 'is_active'
    ];

    protected $casts = [
        'featured_date' => 'date',
        'is_active' => 'boolean'
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