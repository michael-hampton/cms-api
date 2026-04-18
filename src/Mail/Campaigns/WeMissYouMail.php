<?php

namespace App\Mail\Campaigns;

class WeMissYouMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("We miss you, {$this->memberFirstName()} 👋")
            ->markdown('emails.campaigns.we-miss-you', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'exploreUrl' => $this->siteUrl() . '/explore',
            ]);
    }
}