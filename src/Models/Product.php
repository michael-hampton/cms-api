<?php

namespace App\Models;

use App\Contracts\Boost\BoostableInterface;
use App\Framework\Database\QueryBuilder;
use App\Models\Concerns\HasCloneHistory;
use App\Models\Concerns\HasRegionSetVisibility;
use App\Models\Concerns\HasStockHelpers;
use App\Models\Concerns\IsBoostable;
use App\Models\Concerns\Stockable;
use App\Models\Concerns\TracksCreator;
use App\Services\Billing\Preorder\Contracts\AvailabilityPolicyInterface;
use App\Services\Billing\Preorder\PhysicalProductAvailabilityPolicy;

class Product extends Model implements BoostableInterface, Stockable
{
    use HasCloneHistory, TracksCreator, IsBoostable, HasRegionSetVisibility, HasStockHelpers;

    const BOOSTABLE_TYPE = 'product';

    protected $fillable = [
        'name',
        'description',
        'price',
        'sale_price',
        'category_id',
        'brand_id',
        'image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'deleted_at',
        'slug',
        'site_id',
        'is_active',
        'clone_history',
        'created_by',
        'updated_by',
        'stock_quantity',
        'created_at',
        'updated_at',
        'in_stock',
        'preorder_enabled',
        'preorder_restock_date',
        'dispatch_days'
    ];

    protected $casts = [
        'price' => 'float',
        'sale_price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'clone_history' => 'array',
        'preorder_enabled' => 'boolean',
        'preorder_restock_date' => 'datetime',
    ];

    protected $table = 'products';

    protected $appends = ['discount_percentage'];

    public function getDiscountPercentageAttribute(): int
    {
        if ($this->price == 0) {
            return 0;
        }

        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category_id', $category);
    }

    public function scopeByBrand($query, int $brand)
    {
        return $query->where('brand_id', $brand);
    }

    public function scopeOnSale($query)
    {
        return $query->whereColumn('sale_price', '<', 'price');
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('brand_id', 'like', "%{$search}%");
        });
    }

    public function scopeBySlug(QueryBuilder $query, string $slug): QueryBuilder
    {
        return $query->where('slug', $slug);
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', true);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews()
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    public function getAverageRatingAttribute(): float
    {
        $avg = $this->approvedReviews()->avg('rating');
        return $avg ? round((float)$avg, 1) : 0.0;
    }

    public function getReviewCountAttribute(): int
    {
        return $this->approvedReviews()->count();
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)?->where('is_primary', true);
    }

    public function merchants()
    {
        return $this->hasMany(ProductMerchant::class);
    }

    public function availableMerchants()
    {
        return $this->hasMany(ProductMerchant::class)->where('is_available', true);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function activeVariants()
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true);
    }

    public function specifications()
    {
        return $this->hasMany(ProductSpecification::class, null, null, true)
            ->with(['specificationGroup'])
            ->orderBy('sort_order')
            ->get();
    }

    public function inclusions()
    {
        return $this->hasMany(ProductInclusion::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function priceHistory()
    {
        return $this->hasMany(ProductPriceHistory::class)->orderBy('recorded_at', 'desc');
    }

    public function getMainImageUrlAttribute(): ?string
    {
        return $this->primaryImage?->url ?? $this->images->first()?->url ?? $this->image;
    }

    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class, 'product_voucher');
    }

    public function activeVouchers()
    {
        return $this->belongsToMany(Voucher::class, 'product_voucher')
            ->where('status', 'active')
            ->where(function($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            });
    }

    public function merchantLookups()
    {
        return $this->belongsToMany(
            Merchant::class,
            'product_merchants',
            'product_id',
            'merchant_id'
        )->withPivot(['url', 'price', 'is_available', 'variant_id', 'last_price_check']);
    }

    public function badges($relation = false)
    {
        return $this->hasMany(ProductBadge::class, 'product_id', 'id', $relation)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function activeBadges($relation = false)
    {
        return $this->badges($relation)->where(function ($query) {
            $query->whereNull('valid_from')
                ->orWhere('valid_from', '<=', now());
        })->where(function ($query) {
            $query->whereNull('valid_until')
                ->orWhere('valid_until', '>=', now());
        });
    }

    public function specificationGroups()
    {
        return $this->belongsToMany(
            ProductSpecificationGroup::class,
            'product_specifications',
            'product_id',
            'specification_group_id'
        )->distinct();
    }

    /**
     * The RegionSets this product is restricted to.
     * Empty = globally visible. One or more = restricted to members in those regions.
     */
    public function regionSets(bool $relation = false)
    {
        return $this->belongsToMany(RegionSet::class, 'product_region_sets', 'product_id', 'region_set_id', $relation);
    }

    public function availabilityPolicy(): AvailabilityPolicyInterface
    {
        return new PhysicalProductAvailabilityPolicy($this);
    }

    public function stockAlerts()
    {
        return $this->hasMany(ProductStockAlert::class);
    }

    public function isEligibleForBoost(): bool
    {
        return (bool)$this->is_active && $this->isInStock();
    }

    public function scopeBoostable($query)
    {
        return $query
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0);
    }
}