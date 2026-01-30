<?php

namespace App\Models;

use App\Repositories\Rewards\RewardAuditLogRepository;

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
        'slug',
        'admin_notes',
        'declined_by_admin_id',
        'declined_at',
        'decline_reason'
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'reward_data' => 'array',
        'declined_at' => 'datetime'
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

        $oldStatus = $this->status;
        $oldData = $this->toArray();

        $result = $this->update([
            'status' => 'claimed',
            'claimed_at' => now_datetime()->toDateTimeString()
        ]);

        if ($result) {
            // Log the claim
            $auditRepo = app(RewardAuditLogRepository::class);
            $auditRepo->logAction(
                memberRewardId: $this->id,
                action: 'claimed',
                userId: null,
                oldStatus: $oldStatus,
                newStatus: 'claimed',
                oldData: $oldData,
                newData: $this->fresh()->toArray(),
                rewardDefinitionId: $this->reward_definition_id
            );
        }

        return $result;
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

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function decline(int $adminId, string $reason, ?string $notes = null): bool
    {
        $oldStatus = $this->status;
        $oldData = $this->toArray();

        $result = $this->update([
            'status' => 'declined',
            'declined_by_admin_id' => $adminId,
            'declined_at' => now_datetime()->toDateTimeString(),
            'decline_reason' => $reason,
            'admin_notes' => $notes
        ]);

        if ($result) {
            // Log the decline
            $auditRepo = app(RewardAuditLogRepository::class);
            $auditRepo->logAction(
                memberRewardId: $this->id,
                action: 'declined',
                userId: $adminId,
                oldStatus: $oldStatus,
                newStatus: 'declined',
                oldData: $oldData,
                newData: $this->fresh()->toArray(),
                notes: "Reason: $reason" . ($notes ? " | Notes: $notes" : ""),
                rewardDefinitionId: $this->reward_definition_id
            );
        }

        return $result;
    }

    public function declinedBy($relation = false)
    {
        return $this->belongsTo(User::class, 'declined_by_admin_id', 'id', $relation);
    }
}