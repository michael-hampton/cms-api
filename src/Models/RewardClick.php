<?php

namespace App\Models;

class RewardClick extends Model
{
    protected $table = 'reward_clicks';

    protected $fillable = [
        'member_reward_id',
        'member_id',
        'site_id',
        'action',
        'ip_address',
        'user_agent'
    ];

    public function memberReward($relation = false)
    {
        return $this->belongsTo(MemberReward::class, 'member_reward_id', 'id', $relation);
    }

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }
}