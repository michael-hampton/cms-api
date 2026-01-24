<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Models\Concerns\TracksCreator;

class ProductOffer extends Model
{
    use TracksCreator;

    protected $fillable = [
        'product_id',
        'merchant_id',
        'sale_price',
        'start_date',
        'end_date',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sale_price' => 'float',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $table = 'product_offers';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeForProduct(QueryBuilder $query, int $productId): QueryBuilder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForCategory(QueryBuilder $query, int $categoryId): QueryBuilder
    {
        return $query->whereHas('product', function ($q) use ($categoryId) {
            $q->where('category_id', $categoryId);
        });
    }

    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }


        $now = now_datetime();
        return $this->start_date <= $now && $this->end_date >= $now;
    }

    public function getDiscountPercentageAttribute(): int
    {
        if (!$this->product || $this->product->price == 0) {
            return 0;
        }

        return (int)round((($this->product->price - $this->sale_price) / $this->product->price) * 100);
    }
}