<?php

namespace App\Models;

use App\Enums\Orders\OrderLineStatus;

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
        'metadata',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'status',
        'expected_ship_date',
        'quantity_allocated',
        'preorder_enabled'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'subtotal' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'metadata' => 'json',
        'expected_ship_date' => 'datetime',
        'quantity_allocated' => 'integer'
    ];

    public function order($relation = false)
    {
        return $this->belongsTo(Order::class, 'order_id', 'id', $relation);
    }

    public function product($relation = false)
    {
        return $this->belongsTo(Product::class, 'product_id', 'id', $relation);
    }

    public function subscription($relation = false)
    {
        return $this->belongsTo(Subscription::class, 'one_time_subscription_id', 'id', $relation);
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

    public function isPreorder(): bool
    {
        return $this->status === OrderLineStatus::PENDING_PREORDER;
    }

    public function isFullyAllocated(): bool
    {
        return $this->quantity_allocated === $this->quantity;
    }

    public function getRemainingQuantity(): int
    {
        return $this->quantity - $this->quantity_allocated;
    }
}