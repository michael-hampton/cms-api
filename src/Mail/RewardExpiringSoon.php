<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\MemberReward;

class RewardExpiringSoon extends Mailable
{
    public function __construct(
        public Member       $member,
        public MemberReward $reward,
        public int          $daysUntilExpiry
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("⏰ Your Reward Expires Soon - Don't Miss Out!")
            ->markdown('emails.rewards.expiring-soon')
            ->with([
                'member' => $this->member,
                'reward' => $this->reward,
                'daysUntilExpiry' => $this->daysUntilExpiry,
                'expiresAt' => $this->reward->expires_at,
                'rewardData' => $this->reward->reward_data,
            ]);
    }
}