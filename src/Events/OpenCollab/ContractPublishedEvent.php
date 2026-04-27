<?php

namespace App\Events\OpenCollab;

use App\Models\Contract;

/**
 * Fired when a new contract version is created for a site.
 *
 * Listeners must:
 *   1. Invalidate (syncStatus) all contributors on this site.
 *   2. Notify affected contributors that their signature is required.
 *
 * This event is the trigger for the compliance loop:
 *   Contract published → users become incomplete → user fixes → complete again.
 */
class ContractPublishedEvent
{
    public function __construct(
        public readonly Contract $contract,
        public readonly int      $siteId,
        public readonly int $userId
    )
    {
    }
}