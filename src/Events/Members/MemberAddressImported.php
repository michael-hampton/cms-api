<?php

namespace App\Events\Members;

use App\Models\Member;

/**
 * Fired when a member's address is imported via an external integration
 * (e.g. Salesforce sync, CSV import).
 * Listeners use this to trigger territory recalculation.
 */
class MemberAddressImported
{
    public function __construct(
        public readonly Member $member,
    )
    {
    }
}