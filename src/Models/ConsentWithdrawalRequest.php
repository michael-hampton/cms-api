<?php

namespace App\Models;

class ConsentWithdrawalRequest extends Model
{
    protected $table = 'consent_withdrawal_requests';

    protected $fillable = [
        'member_id',
        'type',
        'consent_types',
        'status',
        'requested_at',
        'completed_at',
        'notes',
        'processed_by'
    ];

    protected $casts = [
        'consent_types' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByMember($query, int $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}