<?php

namespace App\Mail\Campaigns;

class StartEngagingMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("There's a lot happening — jump in, {$this->memberFirstName()}! 💬")
            ->markdown('emails.campaigns.start-engaging', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'exploreUrl' => $this->siteUrl() . '/explore',
            ]);
    }
}