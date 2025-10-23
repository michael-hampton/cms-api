<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'subtotal',
        'tax',
        'shipping',
        'discount',
        'total',
        'currency',
        'customer_notes',
        'admin_notes',
        'shipping_address',
        'billing_address',
        'payment_method',
        'payment_status',
        'completed_at',
        'cancelled_at',
        'site_id'
    ];

    protected $casts = [
        'subtotal' => 'float',
        'tax' => 'float',
        'shipping' => 'float',
        'discount' => 'float',
        'total' => 'float',
        'shipping_address' => 'json',
        'billing_address' => 'json',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $dates = ['deleted_at'];

    public function items($relation = false)
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id', $relation);
    }

    public function user($relation = false)
    {
        return $this->belongsTo(User::class, 'user_id', 'id', $relation);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function scopeByStatus(QueryBuilder $query, string $status): QueryBuilder
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus(QueryBuilder $query, string $paymentStatus): QueryBuilder
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function scopeByUser(QueryBuilder $query, int $userId): QueryBuilder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCompleted(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'pending');
    }

    public function getFormattedTotalAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->total, 2);
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['formatted_total'] = $this->getFormattedTotalAttribute();
        $data['is_paid'] = $this->isPaid();
        $data['can_be_cancelled'] = $this->canBeCancelled();

        if ($this->relationLoaded('items')) {
            $data['items'] = $this->items->toArray();
        }

        if ($this->relationLoaded('user')) {
            $data['user'] = $this->user ? $this->user : null;
        }

        return $data;
    }
}