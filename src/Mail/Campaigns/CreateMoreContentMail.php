<?php

namespace App\Mail\Campaigns;

class CreateMoreContentMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("Your contributions are making a difference 🌟")
            ->markdown('emails.campaigns.create-more-content', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'createUrl' => $this->siteUrl() . '/content/create',
            ]);
    }
}