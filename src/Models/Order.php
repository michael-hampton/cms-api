<?php

namespace App\Models;

use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
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
        'clone_history',
        'metadata',
        'checkout_id',
        'one_time_subscription_id',
        'payment_intent_id',
        'stripe_customer_id'
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
        'metadata' => 'array'
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
        $data['can_be_refunded'] = $this->canBeRefunded();
        $data['total_refunded'] = $this->getTotalRefundedAttribute();
        $data['remaining_refundable'] = $this->getRemainingRefundableAttribute();
        $data['is_fully_refunded'] = $this->isFullyRefunded();

        if ($this->relationLoaded('items')) {
            $data['items'] = $this->items->toArray();
        }

        if ($this->relationLoaded('user')) {
            $data['user'] = $this->user ? $this->user : null;
        }

        if ($this->relationLoaded('history')) {
            $data['history'] = $this->history->toArray();
        }

        if ($this->relationLoaded('refunds')) {
            $data['refunds'] = $this->refunds->toArray();
        }

        if ($this->relationLoaded('payments')) {
            $data['payments'] = $this->payments->toArray();
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
        if ($newStatus === OrderStatus::COMPLETED->value && !$this->completed_at) {
            $this->completed_at = date('Y-m-d H:i:s');
        }

        if ($newStatus === OrderStatus::CANCELLED->value && !$this->cancelled_at) {
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
        // Can't refund cancelled or already refunded orders
        if (in_array($this->status, [OrderStatus::CANCELLED->value, OrderStatus::REFUNDED->value])) {
            return false;
        }

        // Can't refund unpaid orders
        if ($this->payment_status !== PaymentStatus::PAID->value) {
            return false;
        }

        if ($this->getTotalRefundedAttribute() >= $this->total) {
            return false;
        }

        return true;
    }

    public function getTotalRefundedAttribute(): float
    {
        if ($this->relationLoaded('refunds')) {
            return $this->refunds
                ->where('status', 'processed')
                ->sum('refund_amount');
        }
        return 0.0;
    }

    public function refunds($relation = false)
    {
        return $this->hasMany(Refund::class, 'order_id', 'id', $relation);
    }

    public function getRemainingRefundableAttribute(): float
    {
        return max(0, $this->total - $this->getTotalRefundedAttribute());
    }

    public function isFullyRefunded(): bool
    {
        return $this->getTotalRefundedAttribute() >= $this->total;
    }

    public function payments($relation = false)
    {
        return $this->hasMany(Payment::class, 'order_id', 'id', $relation);
    }

    public function getLatestPayment(): ?Payment
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->sortByDesc('created_at')->first();
        }

        return Payment::where('order_id', $this->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function hasSuccessfulPayment(): bool
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->where('status', 'completed')->count() > 0;
        }

        return Payment::where('order_id', $this->id)
            ->where('status', 'completed')
            ->exists();
    }

    public function getSubscriptionDetails(): ?array
    {
        if (!$this->one_time_subscription_id) {
            return null;
        }

        $subscription = Subscription::find($this->one_time_subscription_id);
        if (!$subscription) {
            return null;
        }

        $start = $subscription->start_date;
        $end = $subscription->end_date;
        $interval = $start->diff($end);
        $months = ($interval->y * 12) + $interval->m;

        return [
            'subscription' => $subscription,
            'months' => $months,
            'issues' => $months, // Assuming monthly issues
            'type' => $subscription->delivery_type,
            'start_date' => $subscription->start_date,
            'end_date' => $subscription->end_date
        ];
    }
}