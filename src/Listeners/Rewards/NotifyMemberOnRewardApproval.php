<?php

namespace App\Listeners\Rewards;

use App\Events\Rewards\MemberRewardApproved;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Rewards\RewardReadyToClaimMail;

class NotifyMemberOnRewardApproval
{
    public function __construct(private readonly MailManager $mailer)
    {
    }

    public function handle(MemberRewardApproved $event): void
    {
        $member = $event->memberReward->member(true)->first();

        if ($member === null) {
            Logger::error('Could not send reward approval notification: member not found', [
                'member_reward_id' => $event->memberReward->id,
                'member_id' => $event->memberReward->member_id,
            ]);

            return;
        }

        $this->mailer->to($member->email)->send(
            new RewardReadyToClaimMail($event->memberReward, $event->order)
        );
    }
}