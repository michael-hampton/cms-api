<?php

namespace App\Events\Members;

use App\Models\Member;

/**
 * Fired after a member's postcode is updated.
 * Listeners use this to re-derive and persist the member's territory.
 */
class MemberPostcodeUpdated
{
    public function __construct(
        public readonly Member  $member,
        public readonly string  $newPostcode,
        public readonly ?string $previousPostcode = null,
    )
    {
    }
}