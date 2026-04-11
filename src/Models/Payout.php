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
        'reference',
        'notes',
        'approved_by',
        'approved_at',
        'paid_by',
        'processed_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'integer',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'rejected_at' => 'datetime',
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

    public function user($relation = false)
    {
        return $this->belongsTo(User::class, 'user_id', 'id', $relation);
    }
}