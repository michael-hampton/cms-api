<?php

namespace App\Models;

use App\Enums\PaymentStatus;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'order_id',
        'site_id',
        'payment_method',
        'payment_provider',
        'transaction_id',
        'payment_intent_id',
        'status',
        'amount',
        'currency',
        'metadata',
        'error_message',
        'paid_at',
        'failed_at',
        'created_at',
        'updated_at',
        'subscription_id'
    ];

    protected $casts = [
        'amount' => 'float',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order($relation = false)
    {
        return $this->belongsTo(Order::class, 'order_id', 'id', $relation);
    }

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function isCancelled(): bool
    {
        return $this->status === PaymentStatus::CANCELLED->value;
    }

    public function isRefunded(): bool
    {
        return $this->status === PaymentStatus::REFUNDED->value;
    }

    public function markAsPaid(): bool
    {
        $this->status = PaymentStatus::COMPLETED->value;
        $this->paid_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    public function markAsFailed(string $errorMessage): bool
    {
        $this->status = PaymentStatus::FAILED->value;
        $this->error_message = $errorMessage;
        $this->failed_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['is_pending'] = $this->isPending();
        $data['is_processing'] = $this->isProcessing();
        $data['is_completed'] = $this->isCompleted();
        $data['is_failed'] = $this->isFailed();
        $data['can_be_retried'] = $this->canBeRetried();
        $data['can_be_refunded'] = $this->canBeRefunded();

        if ($this->relationLoaded('order')) {
            $data['order'] = $this->order ? $this->order->toArray() : null;
        }

        return $data;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING->value;
    }

    public function isProcessing(): bool
    {
        return $this->status === PaymentStatus::PROCESSING->value;
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::COMPLETED->value;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED->value;
    }

    public function canBeRetried(): bool
    {
        return in_array($this->status, [
            PaymentStatus::FAILED->value,
            PaymentStatus::CANCELLED->value
        ]);
    }

    public function canBeRefunded(): bool
    {
        return $this->status === PaymentStatus::COMPLETED->value;
    }

    public function subscription($relation = false)
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id', $relation);
    }

// Add this method to check if payment is for subscription
    public function isSubscriptionPayment(): bool
    {
        return $this->subscription_id !== null;
    }
}