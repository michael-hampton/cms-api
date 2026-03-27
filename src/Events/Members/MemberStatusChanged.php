<?php

namespace App\Events\Members;

use App\Enums\Member\MemberStatus;
use App\Models\Member;

class MemberStatusChanged
{
    public function __construct(
        public readonly Member       $member,
        public readonly MemberStatus $newStatus,
        public readonly MemberStatus $previousStatus,
    )
    {
    }
}