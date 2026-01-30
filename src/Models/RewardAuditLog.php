<?php

namespace App\Models;

class RewardAuditLog extends Model
{
    protected $table = 'reward_audit_logs';

    protected $fillable = [
        'member_reward_id',
        'reward_definition_id',
        'user_id',
        'action',
        'old_status',
        'new_status',
        'old_data',
        'new_data',
        'notes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function memberReward($relation = false)
    {
        return $this->belongsTo(MemberReward::class, 'member_reward_id', 'id', $relation);
    }

    public function rewardDefinition($relation = false)
    {
        return $this->belongsTo(RewardDefinition::class, 'reward_definition_id', 'id', $relation);
    }

    public function user($relation = false)
    {
        return $this->belongsTo(User::class, 'user_id', 'id', $relation);
    }

    public function getChangedFields(): array
    {
        $changed = [];

        if (!$this->old_data || !$this->new_data) {
            return $changed;
        }

        foreach ($this->new_data as $key => $newValue) {
            $oldValue = $this->old_data[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changed[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changed;
    }
}