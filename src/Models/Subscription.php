<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'member_id',
        'site_id',
        'plan_name',
        'status',
        'start_date',
        'end_date',
        'auto_renew',
        'price',
        'currency'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'auto_renew' => 'boolean',
        'price' => 'float'
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' &&
            ($this->end_date === null || strtotime($this->end_date) > time());
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
            ($this->end_date !== null && strtotime($this->end_date) <= time());
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'active');
    }

    public function scopeByMember(QueryBuilder $query, int $memberId): QueryBuilder
    {
        return $query->where('member_id', $memberId);
    }
}