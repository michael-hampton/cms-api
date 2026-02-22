<?php

namespace App\Models;

use App\Enums\Rewards\RewardStatus;
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
        'decline_reason',
        'deleted_at'
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'reward_data' => 'array',
        'declined_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

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

    public function declinedBy($relation = false)
    {
        return $this->belongsTo(User::class, 'declined_by_admin_id', 'id', $relation);
    }

    // -------------------------------------------------------------------------
    // State queries
    // -------------------------------------------------------------------------

    public function isClaimed(): bool
    {
        return $this->status === RewardStatus::CLAIMED->value;
    }

    public function isPending(): bool
    {
        return $this->status === RewardStatus::PENDING->value;
    }

    public function isApproved(): bool
    {
        return $this->status === RewardStatus::APPROVED->value;
    }

    /**
     * A reward is claimable when it is either pending (criteria-based) or
     * approved (product-linked, auto-approved on order completion).
     */
    public function isClaimable(): bool
    {
        return $this->isPending() || $this->isApproved();
    }

    public function isExpired(): bool
    {
        return $this->status === RewardStatus::EXPIRED->value ||
            ($this->expires_at && $this->expires_at < now_datetime());
    }

    public function isDeclined(): bool
    {
        return $this->status === RewardStatus::DECLINED->value;
    }

    // -------------------------------------------------------------------------
    // State transitions
    // -------------------------------------------------------------------------

    /**
     * Claim this reward.
     *
     * Accepts both 'pending' and 'approved' statuses so that product-linked
     * rewards (which are auto-approved on order completion) can be claimed by
     * the member without any further action from the system.
     */
    public function claim(): bool
    {
        if (!$this->isClaimable() || $this->isExpired()) {
            return false;
        }

        $oldStatus = $this->status;
        $oldData = $this->toArray();

        $result = $this->update([
            'status' => RewardStatus::CLAIMED->value,
            'claimed_at' => now_datetime()->toDateTimeString(),
        ]);

        if ($result) {
            $auditRepo = app(RewardAuditLogRepository::class);
            $auditRepo->logAction(
                memberRewardId: $this->id,
                action: RewardStatus::CLAIMED->value,
                userId: null,
                oldStatus: $oldStatus,
                newStatus: RewardStatus::CLAIMED->value,
                oldData: $oldData,
                newData: $this->fresh()->toArray(),
                rewardDefinitionId: $this->reward_definition_id
            );
        }

        return $result;
    }

    public function decline(int $adminId, string $reason, ?string $notes = null): bool
    {
        $oldStatus = $this->status;
        $oldData = $this->toArray();

        $result = $this->update([
            'status' => RewardStatus::DECLINED->value,
            'declined_by_admin_id' => $adminId,
            'declined_at' => now_datetime()->toDateTimeString(),
            'decline_reason' => $reason,
            'admin_notes' => $notes,
        ]);

        if ($result) {
            $auditRepo = app(RewardAuditLogRepository::class);
            $auditRepo->logAction(
                memberRewardId: $this->id,
                action: RewardStatus::DECLINED->value,
                userId: $adminId,
                oldStatus: $oldStatus,
                newStatus: RewardStatus::DECLINED->value,
                oldData: $oldData,
                newData: $this->fresh()->toArray(),
                notes: "Reason: $reason" . ($notes ? " | Notes: $notes" : ''),
                rewardDefinitionId: $this->reward_definition_id
            );
        }

        return $result;
    }
}