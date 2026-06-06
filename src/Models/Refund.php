<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class Refund extends Model
{
    protected $table = 'refunds';

    protected $fillable = [
        'order_id',
        'refund_type',
        'refund_amount',
        'reason',
        'internal_notes',
        'notify_customer',
        'restock_items',
        'status',
        'processed_by',
        'processed_at',
        'created_at',
        'updated_at',
        'site_id',
        'stripe_refund_id',
        'stripe_refund_status',
        'stripe_refunded_at',
        'stripe_failure_reason'
    ];

    protected $casts = [
        'refund_amount' => 'float',
        'notify_customer' => 'boolean',
        'restock_items' => 'boolean',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function order($relation = false)
    {
        return $this->belongsTo(Order::class, 'order_id', 'id', $relation);
    }

    public function items($relation = false)
    {
        return $this->hasMany(RefundItem::class, 'refund_id', 'id', $relation);
    }

    public function processedBy($relation = false)
    {
        return $this->belongsTo(\App\Models\User::class, 'processed_by', 'id', $relation);
    }

    public function scopeByOrder(QueryBuilder $query, int $orderId): QueryBuilder
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeByStatus(QueryBuilder $query, string $status): QueryBuilder
    {
        return $query->where('status', $status);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getFormattedAmountAttribute(): string
    {
        if (!$this->amount) {
            return '';
        }

        return number_format((float)($this->amount ?? 0), 2);
    }
}