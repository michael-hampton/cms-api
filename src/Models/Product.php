<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class Product extends Model
{
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
        'site_id'
    ];

    protected $casts = [
        'price' => 'float',
        'sale_price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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
}