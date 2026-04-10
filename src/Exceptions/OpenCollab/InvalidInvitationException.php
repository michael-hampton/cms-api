<?php

namespace App\Exceptions\OpenCollab;

use App\Enums\OpenCollab\InvitationStatus;

class InvalidInvitationException extends \RuntimeException
{
    public function __construct(
        private readonly InvitationStatus $reason = InvitationStatus::Expired,
    )
    {
        parent::__construct("Invitation is {$reason->value}.");
    }

    public function getReason(): InvitationStatus
    {
        return $this->reason;
    }
}