<?php

namespace App\Events\Members;

class RewardClaimedByMember
{
    public function __construct(
        public readonly int $memberId,
        public readonly int $rewardId,
        public readonly int $siteId,
    )
    {
    }
}