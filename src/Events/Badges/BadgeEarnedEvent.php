<?php

namespace App\Events\Badges;

use App\Models\Member;
use App\Models\Badge;
use App\Models\MemberBadge;

class BadgeEarnedEvent
{
    public function __construct(
        public readonly Member      $member,
        public readonly Badge       $badge,
        public readonly MemberBadge $memberBadge
    )
    {
    }
}