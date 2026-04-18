<?php

namespace App\Mail\Campaigns;

class EarlyAccessMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("You're invited — early access just for you ✨")
            ->markdown('emails.campaigns.early-access', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'earlyAccessUrl' => $this->siteUrl() . '/early-access',
            ]);
    }
}