<?php

namespace App\Models;

class MemberReward extends Model
{
    protected $table = 'member_rewards';

    protected $fillable = [
        'member_id',
        'reward_definition_id',
        'site_id',
        'status',
        'earned_at',
        'claimed_at',
        'expires_at',
        'reward_data',
        'notes',
        'slug'
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'reward_data' => 'array'
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function rewardDefinition($relation = false)
    {
        return $this->belongsTo(RewardDefinition::class, 'reward_definition_id', 'id', $relation);
    }

    public function voucherCode($relation = false)
    {
        return $this->hasOne(RewardVoucherCode::class, 'member_reward_id', 'id', $relation);
    }

    public function isClaimed(): bool
    {
        return $this->status === 'claimed';
    }

    public function claim(): bool
    {
        if (!$this->isPending() || $this->isExpired()) {
            return false;
        }

        return $this->update([
            'status' => 'claimed',
            'claimed_at' => now_datetime()->toDateTimeString()
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
            ($this->expires_at && $this->expires_at < now_datetime());
    }
}