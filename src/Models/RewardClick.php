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
        'user_agent',
        'channel',
        'surface_type',
        'surface_id',
        'created_at',
        'updated_at',
        'deal_id',
        'clicked_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'clicked_at' => 'datetime',
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