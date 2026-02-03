<?php

namespace App\Events\Rewards;

use App\Models\Member;
use App\Models\MemberReward;

class RewardAwardedEvent
{
    public function __construct(
        public readonly Member       $member,
        public readonly MemberReward $reward
    )
    {
    }
}