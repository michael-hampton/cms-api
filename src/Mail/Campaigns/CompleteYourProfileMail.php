<?php

namespace App\Mail\Campaigns;

class CompleteYourProfileMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("Your account is waiting for you, {$this->memberFirstName()}")
            ->markdown('emails.campaigns.complete-your-profile', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'profileUrl' => $this->siteUrl() . '/account/profile',
            ]);
    }
}