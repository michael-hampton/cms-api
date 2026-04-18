<?php

namespace App\Mail\Campaigns;

class VipRewardsMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("You've unlocked exclusive VIP rewards 🎁")
            ->markdown('emails.campaigns.vip-rewards', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'rewardsUrl' => $this->siteUrl() . '/rewards',
            ]);
    }
}