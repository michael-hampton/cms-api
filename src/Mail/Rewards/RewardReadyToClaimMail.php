<?php

namespace App\Mail\Rewards;

use App\Framework\Mail\Mailable;
use App\Models\MemberReward;
use App\Models\Order;

/**
 * Sent when a product purchase triggers a reward approval,
 * notifying the member that their reward is now ready to claim.
 *
 * Distinct from RewardEarned (which covers criteria-based earning)
 * and RewardClaimed (which confirms a completed claim).
 */
class RewardReadyToClaimMail extends Mailable
{
    public function __construct(
        private readonly MemberReward $memberReward,
        private readonly Order        $order,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $definition = $this->memberReward->rewardDefinition(true)->first();

        return $this
            ->subject("Your reward is ready to claim — Order #{$this->order->order_number}")
            ->markdown('emails.rewards.ready-to-claim')
            ->with([
                'memberReward' => $this->memberReward,
                'order' => $this->order,
                'rewardName' => $definition?->name ?? 'Reward',
                'claimUrl' => $this->buildClaimUrl(),
                'expiresAt' => $this->memberReward->expires_at,
            ]);
    }

    private function buildClaimUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/rewards/' . $this->memberReward->slug . '/claim';
    }
}