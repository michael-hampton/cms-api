<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Framework\Database\QueryBuilder;
use App\Models\Concerns\HasCloneHistory;
use App\Models\Concerns\TracksCreator;

class Order extends Model
{
    use HasCloneHistory, TracksCreator;

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
        'shipping_address_id',
        'billing_address_id',
        'admin_notes',
        'shipping_address',
        'billing_address',
        'payment_method',
        'payment_status',
        'completed_at',
        'cancelled_at',
        'site_id',
        'created_at',
        'voucher_code',
        'clone_history'
    ];

    protected $casts = [
        'subtotal' => 'float',
        'tax' => 'float',
        'shipping' => 'float',
        'discount' => 'float',
        'total' => 'float',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'clone_history' => 'array',
        'shipping_address' => 'array',
        'billing_address' => 'array',
    ];

    protected $dates = ['deleted_at'];

    public function items($relation = false)
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id', $relation);
    }

    public function user($relation = false)
    {
        return $this->belongsTo(Member::class, 'user_id', 'id', $relation);
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
        return $this->currency . ' ' . number_format($this->total ?? 0, 2);
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

        if ($this->relationLoaded('history')) {
            $data['history'] = $this->history->toArray();
        }

        return $data;
    }

    public function getCustomerNameAttribute()
    {
        return $this->user ? $this->user->first_name . ' ' . $this->user->last_name : '';
    }

    public function getCustomerEmailAttribute()
    {
        return $this->user ? $this->user->email : '';
    }

    public function shippingAddress($relation = false)
    {
        return $this->belongsTo(Address::class, 'shipping_address_id', 'id', $relation);
    }

    public function billingAddress($relation = false)
    {
        return $this->belongsTo(Address::class, 'billing_address_id', 'id', $relation);
    }

    public function getShippingAddressDataAttribute(): ?array
    {
        if ($this->relationLoaded('shippingAddress') && $this->shippingAddress) {
            return $this->shippingAddress->toArray();
        }
        return $this->shipping_address;
    }

    public function getBillingAddressDataAttribute(): ?array
    {
        if ($this->relationLoaded('billingAddress') && $this->billingAddress) {
            return $this->billingAddress->toArray();
        }
        return $this->billing_address;
    }

    public function canTransitionTo(OrderStatus|string $targetStatus): bool
    {
        if (!$this->status) {
            return true;
        }

        if (is_string($targetStatus)) {
            $targetStatus = OrderStatus::from($targetStatus);
        }

        $currentStatus = OrderStatus::from($this->status);



        return $currentStatus->canTransitionTo($targetStatus);
    }

    public function changeStatus(OrderStatus|string $newStatus, ?int $userId = null, ?string $notes = null): bool
    {
        if (is_string($newStatus)) {
            $newStatus = OrderStatus::from($newStatus);
        }

        if (!$this->canTransitionTo($newStatus)) {
            throw new \Exception("Cannot transition from {$this->status} to {$newStatus->value}");
        }

        $oldStatus = $this->status;
        $this->status = $newStatus->value;

        // Set timestamps for specific statuses
        if ($newStatus === OrderStatus::COMPLETED && !$this->completed_at) {
            $this->completed_at = date('Y-m-d H:i:s');
        }

        if ($newStatus === OrderStatus::CANCELLED && !$this->cancelled_at) {
            $this->cancelled_at = date('Y-m-d H:i:s');
        }

        return $this->save();
    }

    public function history($relation = false)
    {
        return $this->hasMany(OrderHistory::class, 'order_id', 'id', $relation);
    }

    public function canBeRefunded()
    {
        return true;
    }
}