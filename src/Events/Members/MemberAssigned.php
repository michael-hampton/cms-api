<?php

namespace App\Events\Members;

use App\Models\Member;
use App\Models\User;

class MemberAssigned
{
    public function __construct(
        public readonly Member $member,
        public readonly ?User  $assignedAgent,
        public readonly ?int   $previousAgentId,
    )
    {
    }
}