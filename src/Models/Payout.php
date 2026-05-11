<?php

namespace App\Models;

use App\Enums\OpenCollab\PayoutStatus;

class Payout extends Model
{
    protected $table = 'oc_payouts';

    protected $fillable = [
        'user_id',
        'site_id',
        'amount',
        'currency',
        'status',
        'method',
        'provider',
        'provider_payout_id',
        'provider_transfer_id',
        'provider_status',
        'provider_response_json',
        'processing_attempts',
        'reference',
        'notes',
        'approved_by',
        'approved_at',
        'paid_by',
        'processed_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'processing_attempts' => 'integer',
        'provider_response_json' => 'json',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === PayoutStatus::Pending->value;
    }

    public function isApproved(): bool
    {
        return $this->status === PayoutStatus::Approved->value;
    }

    public function isPaid(): bool
    {
        return $this->status === PayoutStatus::Paid->value;
    }

    public function isRejected(): bool
    {
        return $this->status === PayoutStatus::Rejected->value;
    }

    public function isFailed(): bool
    {
        return $this->status === PayoutStatus::Failed->value;
    }

    public function user($relation = false)
    {
        return $this->belongsTo(User::class, 'user_id', 'id', $relation);
    }
}