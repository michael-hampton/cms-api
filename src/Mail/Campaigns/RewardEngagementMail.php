<?php

namespace App\Mail\Campaigns;

class RewardEngagementMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("You're on a roll — here's your reward 🏆")
            ->markdown('emails.campaigns.reward-engagement', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'rewardsUrl' => $this->siteUrl() . '/rewards',
            ]);
    }
}