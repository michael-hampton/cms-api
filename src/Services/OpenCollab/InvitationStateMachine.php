<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\InvitationStatus;
use App\Models\Invitation;

class InvitationStateMachine
{
    public function __construct(
        private readonly Invitation $invitation
    )
    {
    }

    public function canResend(): bool
    {
        return in_array($this->status(), [
            InvitationStatus::Pending,
            InvitationStatus::Expired,
            InvitationStatus::Revoked,
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

    public function shouldCreateNewInvite(): bool
    {
        return in_array($this->status(), [
            InvitationStatus::Expired,
            InvitationStatus::Revoked,
        ], true);
    }

    public function assertAcceptable(): void
    {
        if ($this->status() !== InvitationStatus::Pending) {
            throw new \DomainException("Invitation cannot be accepted in state {$this->status()->value}");
        }
    }
}