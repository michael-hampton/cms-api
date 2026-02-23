<?php

namespace App\Mail\Rewards;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\MemberReward;

class RewardClaimed extends Mailable
{
    public function __construct(
        public Member       $member,
        public MemberReward $reward
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $rewardType = $this->getRewardDescription();

        return $this
            ->subject("Your {$rewardType} is Ready!")
            ->markdown('emails.rewards.claimed')
            ->with([
                'member' => $this->member,
                'reward' => $this->reward,
                'rewardData' => $this->reward->reward_data,
                'claimedAt' => $this->reward->claimed_at,
            ]);
    }

    private function getRewardDescription(): string
    {
        $rewardData = $this->reward->reward_data;

        if (is_array($rewardData)) {
            if (isset($rewardData['voucher_code'])) {
                return 'Voucher';
            }
            if (isset($rewardData['discount_type'])) {
                return 'Discount Code';
            }
            if (isset($rewardData['points'])) {
                return 'Points';
            }
        }

        return 'Reward';
    }
}