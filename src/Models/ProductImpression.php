<?php

namespace App\Models;

class ProductImpression extends Model
{
    public $timestamps = false;

    protected $table = 'product_impressions';

    protected $fillable = [
        'product_id',
        'context',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function product($relation = false)
    {
        return $this->belongsTo(Product::class, 'product_id', 'id', $relation);
    }
}