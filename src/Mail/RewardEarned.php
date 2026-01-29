<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\MemberReward;

class RewardEarned extends Mailable
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
        $rewardType = $this->getRewardTypeName();

        return $this
            ->subject("🎉 You've Earned a {$rewardType}!")
            ->markdown('emails.rewards.earned')
            ->with([
                'member' => $this->member,
                'reward' => $this->reward,
                'rewardType' => $rewardType,
                'rewardData' => $this->reward->reward_data,
                'expiresAt' => $this->reward->expires_at,
            ]);
    }

    private function getRewardTypeName(): string
    {
        $definition = $this->reward->definition;

        return match ($definition?->reward_type) {
            'voucher' => 'Gift Voucher',
            'discount' => 'Discount',
            'points' => 'Points Reward',
            default => 'Reward'
        };
    }
}