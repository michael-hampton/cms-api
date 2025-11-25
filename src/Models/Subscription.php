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
        'currency',
        'plan_id'
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
            ($this->end_date === null || $this->end_date > new \DateTime());
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
            ($this->end_date !== null && $this->end_date <= new \DateTime());
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'active');
    }

    public function scopeByMember(QueryBuilder $query, int $memberId): QueryBuilder
    {
        return $query->where('member_id', $memberId);
    }

    public function plan($relation = false)
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'id', $relation);
    }

    public function scopeByPlan(QueryBuilder $query, int $planId): QueryBuilder
    {
        return $query->where('plan_id', $planId);
    }

    public function isTrialing(): bool
    {
        if (!$this->plan) {
            return false;
        }

        $trialEnds = (clone $this->start_date)->modify('+' . $this->plan->trial_days . ' days');
        return $this->plan->trial_days > 0 && new \DateTime() <= $trialEnds;
    }

    public function trialEndsAt(): ?\DateTime
    {
        if (!$this->plan || $this->plan->trial_days <= 0) {
            return null;
        }

        return (clone $this->start_date)->modify('+' . $this->plan->trial_days . ' days');
    }
}