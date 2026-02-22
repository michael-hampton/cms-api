<?php

namespace App\Listeners\Rewards;

use App\Events\Rewards\MemberRewardApproved;
use App\Framework\Support\Logger;

/**
 * Example listener: logs (or notifies the member) when a reward is approved.
 *
 * Replace the log call with real notification logic (mail, push, etc.)
 * without touching the event or service.
 */
class NotifyMemberOnRewardApproval
{
    public function handle(MemberRewardApproved $event): void
    {
        // TODO: replace with real notification (e.g. Mail::to(...)->send(...))
        Logger::info('Member reward approved after product purchase', [
            'member_reward_id' => $event->memberReward->id,
            'member_id' => $event->memberReward->member_id,
            'reward_id' => $event->memberReward->reward_id,
            'order_id' => $event->order->id,
        ]);
    }
}