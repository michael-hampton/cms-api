<?php

namespace App\Models;

class RefundItem extends Model
{
    protected $table = 'refund_items';

    protected $fillable = [
        'refund_id',
        'order_item_id',
        'product_id',
        'product_name',
        'quantity',
        'refund_quantity',
        'unit_price',
        'refund_amount',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'refund_quantity' => 'integer',
        'unit_price' => 'float',
        'refund_amount' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function refund($relation = false)
    {
        return $this->belongsTo(Refund::class, 'refund_id', 'id', $relation);
    }

    public function orderItem($relation = false)
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'id', $relation);
    }

    public function product($relation = false)
    {
        return $this->belongsTo(Product::class, 'product_id', 'id', $relation);
    }
}