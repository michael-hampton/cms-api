<?php

namespace App\Models;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'sale_price',
        'category_id',
        'brand',
        'image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'deleted_at'
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

    public function scopeByBrand($query, string $brand)
    {
        return $query->where('brand', $brand);
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
                ->orWhere('brand', 'like', "%{$search}%");
        });
    }
}