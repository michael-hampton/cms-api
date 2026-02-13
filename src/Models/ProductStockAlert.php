<?php

namespace App\Models;

class ProductStockAlert extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'email',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    protected $table = 'product_stock_alerts';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(Member::class, 'user_id');
    }

    public function isPending(): bool
    {
        return $this->notified_at === null;
    }
}