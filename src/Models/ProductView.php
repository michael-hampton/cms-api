<?php

namespace App\Models;

class ProductView extends Model
{
    protected $table = 'product_views';

    protected $fillable = [
        'product_id',
        'user_id',
        'session_id',
        'ip_address',
        'site_id',
        'created_at',
        'viewed_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'viewed_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}