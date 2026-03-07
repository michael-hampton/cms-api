<?php

namespace App\Events\Members;

use App\Models\Member;

/**
 * Fired after a member record is persisted for the first time.
 * Listeners use this to trigger side effects such as territory assignment.
 */
class MemberCreated
{
    public function __construct(
        public readonly Member $member,
    )
    {
    }
}