<?php

namespace App\Models;

use App\Models\Concerns\HasRegionSetVisibility;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $name
 * @property array $attributes
 * @property float $price
 * @property float|null $sale_price
 * @property float $price_modifier
 * @property bool $is_active
 */
class ProductVariant extends Model
{
    use HasRegionSetVisibility;

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
        'stock_quantity',
        'preorder_restock_date',
        'preorder_enabled'
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

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

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

    /**
     * The RegionSets this variant is restricted to.
     * Empty = globally visible. One or more = restricted to members in those regions.
     * Variant region sets are evaluated independently of the parent product's region sets.
     */
    public function regionSets(bool $relation = false)
    {
        return $this->belongsToMany(RegionSet::class, 'product_variant_region_sets', 'product_variant_id', 'region_set_id', $relation);
    }

    // -------------------------------------------------------------------------
    // Computed attributes
    // -------------------------------------------------------------------------

    public function getFinalPriceAttribute(): float
    {
        return $this->sale_price ?? $this->price;
    }

    public function getDiscountPercentageAttribute(): int
    {
        if ($this->sale_price === null || $this->price == 0 || $this->sale_price >= $this->price) {
            return 0;
        }

        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }
}