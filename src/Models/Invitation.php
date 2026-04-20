<?php

/**
 * ── MIGRATION: add accepted_by and accepted_at to oc_invitations ──────────────
 *
 * ALTER TABLE oc_invitations
 *   ADD COLUMN accepted_by INT NULL AFTER invited_by,
 *   ADD COLUMN accepted_at DATETIME NULL AFTER accepted_by,
 *   ADD CONSTRAINT fk_invitation_accepted_by
 *     FOREIGN KEY (accepted_by) REFERENCES users(id) ON DELETE SET NULL;
 *
 * accepted_by = user ID of whoever accepted the invitation.
 *               This is always set — it's the newly created user for self-service
 *               acceptance, or the admin user ID for admin-on-behalf acceptance.
 * accepted_at = timestamp of acceptance.
 *
 * The existing used_at column records WHEN the invitation was marked consumed.
 * accepted_by / accepted_at record WHO did the consuming and when.
 * Both should be set together in the same transaction.
 */

namespace App\Models;

use App\Enums\OpenCollab\InvitationStatus;

class Invitation extends Model
{
    protected $table = 'oc_invitations';

    protected $fillable = [
        'site_id',
        'email',
        'token',
        'invited_by',
        'accepted_by',   // ← NEW: user ID who accepted (self or admin)
        'accepted_at',   // ← NEW: when it was accepted
        'expires_at',
        'used_at',
        'revoked_at',
        'revoked_by',
        'created_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'accepted_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        return $this->expires_at < new \DateTime();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Resolves the current status without throwing.
     */
    public function resolveStatus(): InvitationStatus
    {
        if ($this->used_at !== null) {
            return InvitationStatus::Used;
        }

        if ($this->revoked_at !== null) {
            return InvitationStatus::Revoked;
        }

        if ($this->expires_at !== null && $this->expires_at < new \DateTime()) {
            return InvitationStatus::Expired;
        }

        return InvitationStatus::Pending;
    }

    /**
     * The user who sent the invitation.
     */
    public function invitedByUser($relation = false)
    {
        return $this->belongsTo(User::class, 'invited_by', 'id', $relation);
    }

    /**
     * The user who accepted the invitation.
     * May be the new contributor (self-acceptance) or an admin (on-behalf acceptance).
     */
    public function acceptedByUser($relation = false)
    {
        return $this->belongsTo(User::class, 'accepted_by', 'id', $relation);
    }
}