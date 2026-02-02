<?php

namespace App\Models;

class Shipment extends Model
{
    protected $table = 'shipments';

    protected $fillable = [
        'order_id',
        'checkout_id',
        'merchant_id',
        'shipping_cost',
        'country',
        'status',
        'metadata',
        'site_id',
    ];

    protected $casts = [
        'shipping_cost' => 'float',
        'metadata' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}