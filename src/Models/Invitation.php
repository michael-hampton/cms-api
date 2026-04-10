<?php

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
        'expires_at',
        'used_at',
        'revoked_at',
        'revoked_by'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
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

    /**
     * Resolves the current status of an invitation without throwing.
     * Useful when the controller needs to return distinct error messages.
     */
    public function resolveStatus(): InvitationStatus
    {
        if ($this->used_at !== null) {
            return InvitationStatus::Used;
        }

        if ($this->revoked_at !== null) {
            return InvitationStatus::Revoked;
        }

        if ($this->expires_at < new \DateTime()) {
            return InvitationStatus::Expired;
        }

        return InvitationStatus::Pending;
    }
}