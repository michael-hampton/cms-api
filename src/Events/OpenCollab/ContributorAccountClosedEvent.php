<?php

namespace App\Events\OpenCollab;

use App\Models\User;

/**
 * Fired when a contributor account is fully closed by an admin.
 * Distinct from a closure request — this is the actual closure action.
 * Listeners: revoke site access, freeze content, trigger final payout calculation.
 */
class ContributorAccountClosedEvent
{
    public function __construct(
        public readonly User   $contributor,
        public readonly int    $adminId,
        public readonly string $reason,
    )
    {
    }
}