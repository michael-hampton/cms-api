<?php

namespace App\Services\OpenCollab;

use App\Models\Invitation;

class InvitationStateMachineFactory
{
    public function make(Invitation $invitation): InvitationStateMachine
    {
        return new InvitationStateMachine($invitation);
    }
}