<?php

namespace App\Models;

class ProductSpecification extends Model
{
    protected $table = 'product_specifications';

    protected $fillable = [
        'product_id',
        'category',
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
}