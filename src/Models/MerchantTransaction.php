<?php

namespace App\Models;

class MerchantTransaction extends Model
{
    protected $table = 'merchant_transactions';

    protected $fillable = [
        'merchant_id',
        'order_id',
        'voucher_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'status',
        'description',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the merchant that owns the transaction
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the order associated with the transaction
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the voucher associated with the transaction
     */
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Scope for pending review transactions
     */
    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    /**
     * Scope for failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for voucher funding transactions
     */
    public function scopeVoucherFunding($query)
    {
        return $query->where('type', 'voucher_funding');
    }
}