<?php

namespace App\Models;

class ProductSpecification extends Model
{
    protected $table = 'product_specifications';

    protected $fillable = [
        'product_id',
        'specification_group_id',
        'category', // Keep for backward compatibility
        'key',
        'value',
        'sort_order'
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function specificationGroup()
    {
        return $this->belongsTo(ProductSpecificationGroup::class, 'specification_group_id');
    }

    /**
     * Get the category name (from group or legacy field)
     */
    public function getCategoryNameAttribute(): string
    {
        return $this->specificationGroup?->name ?? $this->category ?? 'General';
    }
}