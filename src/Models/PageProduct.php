<?php

namespace App\Models;

class PageProduct extends Model
{
    protected $table = 'page_products';

    protected $timestamps = false;

    protected $fillable = [
        'page_id',
        'product_id',
        'sort_order',
        'site_id'
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}