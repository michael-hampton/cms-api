<?php
namespace App\Models;

class PriceAlert extends Model
{
    protected $table = 'price_alerts';

    protected $fillable = [
        'user_id', 'email', 'product_id', 'variant_id', 'merchant_id',
        'target_price', 'current_price', 'is_triggered', 'is_notified',
        'triggered_at', 'notified_at'
    ];

    protected $casts = [
        'target_price' => 'float',
        'current_price' => 'float',
        'is_triggered' => 'boolean',
        'is_notified' => 'boolean',
        'triggered_at' => 'datetime',
        'notified_at' => 'datetime'
    ];
}