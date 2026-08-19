<?php

namespace App\Events\OpenCollab;

use App\Models\Invitation;
use App\Models\User;

/**
 * Fired when a contributor accepts their invitation and their account is created.
 *
 * Listeners should handle:
 *   - Welcome email to the new contributor
 *   - Admin notification that a new contributor has joined
 *   - Activity feed entry
 */
class InvitationAccepted
{
    public function __construct(
        public readonly User       $user,
        public readonly Invitation $invitation,
        public readonly bool       $acceptedOnBehalf = false,
    )
    {
    }
}