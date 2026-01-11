<?php

namespace App\Models;

class RewardVoucherCode extends Model
{
    protected $table = 'reward_voucher_codes';

    protected $fillable = [
        'reward_definition_id',
        'site_id',
        'voucher_code',
        'provider',
        'value',
        'currency',
        'assigned_to_member_id',
        'member_reward_id',
        'assigned_at',
        'is_used'
    ];

    protected $casts = [
        'value' => 'float',
        'assigned_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    public function rewardDefinition($relation = false)
    {
        return $this->belongsTo(RewardDefinition::class, 'reward_definition_id', 'id', $relation);
    }

    public function assignedTo($relation = false)
    {
        return $this->belongsTo(Member::class, 'assigned_to_member_id', 'id', $relation);
    }

    public function memberReward($relation = false)
    {
        return $this->belongsTo(MemberReward::class, 'member_reward_id', 'id', $relation);
    }

    public function assign(int $memberId, int $memberRewardId): bool
    {
        if ($this->is_used || $this->assigned_to_member_id) {
            return false;
        }

        return $this->update([
            'assigned_to_member_id' => $memberId,
            'member_reward_id' => $memberRewardId,
            'assigned_at' => now_datetime()
        ]);
    }

    public function markAsUsed(): bool
    {
        return $this->update(['is_used' => true]);
    }
}