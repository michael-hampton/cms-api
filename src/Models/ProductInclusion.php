<?php

namespace App\Models;

class ProductInclusion extends Model
{
    protected $table = 'product_inclusions';

    protected $fillable = [
        'product_id',
        'page_id',
        'site_id',
        'included_at'
    ];

    protected $casts = [
        'included_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}