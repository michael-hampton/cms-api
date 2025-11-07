<?php

namespace App\Models;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_name',
        'product_id',
        'product_sku',
        'quantity',
        'unit_price',
        'subtotal',
        'tax',
        'total',
        'metadata'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'subtotal' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'metadata' => 'json'
    ];

    public function order($relation = false)
    {
        return $this->belongsTo(Order::class, 'order_id', 'id', $relation);
    }

    public function product($relation = false)
    {
        return $this->belongsTo(Product::class, 'product_id', 'id', $relation);
    }

    public function getFormattedTotalAttribute(): string
    {
        $order = $this->order;
        $currency = $order ? $order->currency : 'USD';
        return $currency . ' ' . number_format($this->total, 2);
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['formatted_total'] = $this->getFormattedTotalAttribute();

        if ($this->relationLoaded('order')) {
            $data['order'] = $this->order ? $this->order->toArray() : null;
        }

        return $data;
    }
}