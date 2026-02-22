<?php

namespace App\Events\Rewards;

use App\Models\MemberReward;

class MemberRewardApproved
{

    /**
     * @param mixed $memberReward
     * @param Order $order
     */
    public function __construct(public MemberReward $memberReward, public \App\Models\Order $order)
    {
    }
}