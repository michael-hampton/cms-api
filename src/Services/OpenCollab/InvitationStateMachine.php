<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\InvitationStatus;
use App\Models\Invitation;

/**
 * Encapsulates state-transition rules for an invitation.
 *
 * Revoked invitation policy
 * -------------------------
 * A revoked invitation is PERMANENTLY INVALID. The state machine deliberately
 * does NOT allow a new invitation to be created from a revoked one.
 * Only an admin can create a new invitation for a revoked email+site pair.
 *
 * This is reflected in shouldCreateNewInvite() which only returns true for
 * Expired invitations, NOT for Revoked ones.
 */
class InvitationStateMachine
{
    public function __construct(
        private readonly Invitation $invitation
    ) {
    }

    public function canResend(): bool
    {
        // Resending is only meaningful for pending and expired states.
        // Revoked invitations cannot be resent — they are permanently invalid.
        return in_array($this->status(), [
            InvitationStatus::Pending,
            InvitationStatus::Expired,
        ], true);
    }

    public function status(): InvitationStatus
    {
        return $this->invitation->resolveStatus();
    }

    public function isPending(): bool
    {
        return $this->status() === InvitationStatus::Pending;
    }

    public function isUsed(): bool
    {
        return $this->status() === InvitationStatus::Used;
    }

    /**
     * Returns true only for EXPIRED invitations.
     *
     * Revoked invitations are intentionally excluded — creating a new invite
     * from a revoked one would circumvent the administrative decision to revoke.
     */
    public function shouldCreateNewInvite(): bool
    {
        return $this->status() === InvitationStatus::Expired;
    }

    public function assertAcceptable(): void
    {
        if ($this->status() !== InvitationStatus::Pending) {
            throw new \DomainException(
                "Invitation cannot be accepted in state {$this->status()->value}"
            );
        }
    }
}