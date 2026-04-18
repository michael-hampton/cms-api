<?php

namespace App\Mail\Campaigns;

class WelcomeSeriesMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("Welcome! Here's how to get started 🚀")
            ->markdown('emails.campaigns.welcome-series', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'exploreUrl' => $this->siteUrl() . '/explore',
                'profileUrl' => $this->siteUrl() . '/account/profile',
            ]);
    }
}