<?php

namespace App\Events\Badges;

use App\Models\Member;
use App\Models\MemberPoint;

class PointsAwardedEvent
{
    public function __construct(
        public readonly Member      $member,
        public readonly MemberPoint $memberPoint
    )
    {
    }
}